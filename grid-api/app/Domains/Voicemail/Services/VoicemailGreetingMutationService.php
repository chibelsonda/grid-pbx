<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use App\Domains\Voicemail\Contracts\SwitchVoicemailGreetingGateway;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailGreeting;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class VoicemailGreetingMutationService
{
    public function __construct(
        private readonly SwitchVoicemailGreetingGateway $gateway,
        private readonly VoicemailGreetingProjectionService $projection,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
        private readonly AuditService $audit,
    ) {}

    public function store(
        SwitchAccount $account,
        SwitchVoicemailBox $voicemailBox,
        User $actor,
        UploadedFile $audio,
        ?string $name = null,
        ?string $ipAddress = null,
    ): SwitchVoicemailGreeting {
        $previousResourceId = $voicemailBox->unavailableGreeting?->switch_resource_id;
        $newResourceId = null;
        $assigned = false;

        try {
            $created = $this->gateway->create(
                $account,
                $voicemailBox->switch_resource_id,
                $name ?: sprintf('Mailbox %s unavailable greeting', $voicemailBox->mailbox ?? $voicemailBox->getKey()),
                $audio->getClientOriginalName(),
            );
            $newResourceId = $this->requiredResourceId($created);
            $realPath = $audio->getRealPath();

            if ($realPath === false) {
                throw new RuntimeException('Unable to read the uploaded greeting audio.');
            }

            $handle = fopen($realPath, 'rb');

            if ($handle === false) {
                throw new RuntimeException('Unable to read the uploaded greeting audio.');
            }

            $uploaded = $this->gateway->upload(
                $account,
                $newResourceId,
                Utils::streamFor($handle),
                (string) $audio->getMimeType(),
                (int) $audio->getSize(),
            );
            $mailboxSnapshot = $this->gateway->assign(
                $account,
                $voicemailBox->switch_resource_id,
                $newResourceId,
            );
            $assigned = true;

            return DB::transaction(function () use ($account, $voicemailBox, $actor, $audio, $ipAddress, $uploaded, $mailboxSnapshot): SwitchVoicemailGreeting {
                $greeting = $this->projection->project($account, $voicemailBox, $uploaded);
                $voicemailBox->update([
                    'switch_json' => $this->redactSensitiveData->handle($mailboxSnapshot),
                    'last_synced_at' => now(),
                ]);
                $this->audit->record(
                    $actor,
                    $account,
                    'voicemail_greeting.uploaded',
                    'succeeded',
                    $greeting->switch_resource_id,
                    [
                        'voicemail_box_id' => $voicemailBox->getKey(),
                        'content_type' => $greeting->content_type,
                        'content_length' => $greeting->content_length,
                        'original_name' => $audio->getClientOriginalName(),
                    ],
                    $ipAddress,
                    'voicemail_greeting',
                );

                return $greeting;
            });
        } catch (Throwable $exception) {
            if ($assigned) {
                $this->safelyAssign($account, $voicemailBox, $previousResourceId);
            }

            if ($newResourceId !== null) {
                $this->safelyDelete($account, $newResourceId);
            }

            $this->audit->record(
                $actor,
                $account,
                'voicemail_greeting.upload_failed',
                'failed',
                $newResourceId,
                ['voicemail_box_id' => $voicemailBox->getKey(), 'error_type' => $exception::class],
                $ipAddress,
                'voicemail_greeting',
            );

            throw $exception;
        }
    }

    public function detach(
        SwitchAccount $account,
        SwitchVoicemailBox $voicemailBox,
        SwitchVoicemailGreeting $greeting,
        User $actor,
        ?string $ipAddress = null,
    ): void {
        $mailboxSnapshot = $this->gateway->assign($account, $voicemailBox->switch_resource_id, null);

        try {
            DB::transaction(function () use ($account, $voicemailBox, $greeting, $actor, $ipAddress, $mailboxSnapshot): void {
                $greeting->delete();
                $voicemailBox->update([
                    'switch_json' => $this->redactSensitiveData->handle($mailboxSnapshot),
                    'last_synced_at' => now(),
                ]);
                $this->audit->record(
                    $actor,
                    $account,
                    'voicemail_greeting.detached',
                    'succeeded',
                    $greeting->switch_resource_id,
                    ['voicemail_box_id' => $voicemailBox->getKey()],
                    $ipAddress,
                    'voicemail_greeting',
                );
            });
        } catch (Throwable $exception) {
            $this->safelyAssign($account, $voicemailBox, $greeting->switch_resource_id);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function requiredResourceId(array $snapshot): string
    {
        $resourceId = $snapshot['id'] ?? null;

        if (! is_string($resourceId) || $resourceId === '') {
            throw new RuntimeException('Switch media response is missing its resource identifier.');
        }

        return $resourceId;
    }

    private function safelyAssign(
        SwitchAccount $account,
        SwitchVoicemailBox $voicemailBox,
        ?string $resourceId,
    ): void {
        try {
            $this->gateway->assign($account, $voicemailBox->switch_resource_id, $resourceId);
        } catch (Throwable) {
        }
    }

    private function safelyDelete(SwitchAccount $account, string $resourceId): void
    {
        try {
            $this->gateway->delete($account, $resourceId);
        } catch (Throwable) {
        }
    }
}
