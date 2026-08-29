<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use App\Domains\Voicemail\Contracts\SwitchVoicemailBoxGateway;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnexpectedValueException;

class VoicemailBoxMutationService
{
    public function __construct(
        private readonly SwitchVoicemailBoxGateway $gateway,
        private readonly VoicemailMutationDataFactory $mutationData,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(
        SwitchAccount $account,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchVoicemailBox {
        try {
            $snapshot = $this->gateway->create($account, $this->mutationData->make($account, $data));

            return DB::transaction(function () use ($account, $actor, $data, $ipAddress, $snapshot): SwitchVoicemailBox {
                $voicemailBox = $this->project($account, $snapshot);
                $this->audit->record(
                    $actor,
                    $account,
                    'voicemail_box.created',
                    'succeeded',
                    $voicemailBox->switch_resource_id,
                    $this->safeMetadata($data),
                    $ipAddress,
                    'voicemail_box',
                );

                return $voicemailBox;
            });
        } catch (Throwable $exception) {
            $this->recordFailure($actor, $account, 'voicemail_box.create_failed', null, $exception, $ipAddress);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(
        SwitchAccount $account,
        SwitchVoicemailBox $voicemailBox,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchVoicemailBox {
        try {
            $snapshot = $this->gateway->update(
                $account,
                $voicemailBox->switch_resource_id,
                $this->mutationData->make($account, $data, existingVoicemailBox: $voicemailBox),
            );

            return DB::transaction(function () use ($account, $actor, $data, $ipAddress, $snapshot): SwitchVoicemailBox {
                $projected = $this->project($account, $snapshot);
                $this->audit->record(
                    $actor,
                    $account,
                    'voicemail_box.updated',
                    'succeeded',
                    $projected->switch_resource_id,
                    $this->safeMetadata($data),
                    $ipAddress,
                    'voicemail_box',
                );

                return $projected;
            });
        } catch (Throwable $exception) {
            $this->recordFailure(
                $actor,
                $account,
                'voicemail_box.update_failed',
                $voicemailBox->switch_resource_id,
                $exception,
                $ipAddress,
            );
            throw $exception;
        }
    }

    public function delete(
        SwitchAccount $account,
        SwitchVoicemailBox $voicemailBox,
        User $actor,
        ?string $ipAddress = null,
    ): void {
        try {
            $this->gateway->delete($account, $voicemailBox->switch_resource_id);
            DB::transaction(function () use ($account, $actor, $voicemailBox, $ipAddress): void {
                $voicemailBox->delete();
                $this->audit->record(
                    $actor,
                    $account,
                    'voicemail_box.deleted',
                    'succeeded',
                    $voicemailBox->switch_resource_id,
                    ['name' => $voicemailBox->name, 'mailbox' => $voicemailBox->mailbox],
                    $ipAddress,
                    'voicemail_box',
                );
            });
        } catch (Throwable $exception) {
            $this->recordFailure(
                $actor,
                $account,
                'voicemail_box.delete_failed',
                $voicemailBox->switch_resource_id,
                $exception,
                $ipAddress,
            );
            throw $exception;
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function project(SwitchAccount $account, array $snapshot): SwitchVoicemailBox
    {
        $resourceId = $this->stringValue($snapshot['id'] ?? null);

        if ($resourceId === null) {
            throw new UnexpectedValueException('Switch voicemail box response is missing its resource identifier.');
        }

        $ownerResourceId = $this->stringValue($snapshot['owner_id'] ?? null);
        $extensionId = $ownerResourceId === null
            ? null
            : $account->extensions()->where('switch_resource_id', $ownerResourceId)->value('extension_id');
        $voicemailBox = SwitchVoicemailBox::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $resourceId,
        ]);
        $voicemailBox->fill([
            'switch_extension_id' => $extensionId,
            'owner_switch_resource_id' => $ownerResourceId,
            'name' => $this->stringValue($snapshot['name'] ?? null),
            'mailbox' => $this->stringValue($snapshot['mailbox'] ?? null),
            'timezone' => $this->stringValue($snapshot['timezone'] ?? null),
            'notification_emails' => $this->stringList($snapshot['notify_email_addresses'] ?? null),
            'transcribe' => (bool) ($snapshot['transcribe'] ?? false),
            'require_pin' => (bool) ($snapshot['require_pin'] ?? false),
            'is_setup' => array_key_exists('is_setup', $snapshot) ? (bool) $snapshot['is_setup'] : null,
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $voicemailBox->deleted_at = null;
        $voicemailBox->save();

        return $voicemailBox
            ->load(['extension:extension_id,id,display_name,extension', 'unavailableGreeting'])
            ->loadCount([
                'messages',
                'messages as new_messages_count' => fn ($query) => $query->where('folder', 'new'),
                'messages as saved_messages_count' => fn ($query) => $query->where('folder', 'saved'),
                'messages as deleted_messages_count' => fn ($query) => $query->where('folder', 'deleted'),
            ]);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(
            is_array($value) ? $value : [],
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function safeMetadata(array $data): array
    {
        return [
            'name' => $data['name'],
            'mailbox' => $data['mailbox'],
            'assigned_extension_id' => $data['assigned_extension_id'] ?? null,
            'notification_email_count' => count($data['notification_emails']),
            'transcribe' => $data['transcribe'],
            'require_pin' => $data['require_pin'],
            'pin_changed' => isset($data['pin']),
        ];
    }

    private function recordFailure(
        User $actor,
        SwitchAccount $account,
        string $action,
        ?string $resourceId,
        Throwable $exception,
        ?string $ipAddress,
    ): void {
        $this->audit->record(
            $actor,
            $account,
            $action,
            'failed',
            $resourceId,
            ['error_type' => $exception::class],
            $ipAddress,
            'voicemail_box',
        );
    }
}
