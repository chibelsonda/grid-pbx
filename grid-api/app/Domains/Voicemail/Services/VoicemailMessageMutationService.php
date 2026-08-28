<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\Voicemail\Contracts\SwitchVoicemailMessageGateway;
use App\Domains\Voicemail\Enums\VoicemailMessageFolder;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class VoicemailMessageMutationService
{
    public function __construct(
        private readonly SwitchVoicemailMessageGateway $gateway,
        private readonly VoicemailMessageProjectionService $projection,
        private readonly AuditService $audit,
    ) {}

    public function changeFolder(
        SwitchAccount $account,
        SwitchVoicemailBox $voicemailBox,
        SwitchVoicemailMessage $message,
        VoicemailMessageFolder $folder,
        User $actor,
        ?string $ipAddress = null,
    ): SwitchVoicemailMessage {
        $fromFolder = $message->folder;

        try {
            $snapshot = $this->gateway->changeFolder(
                $account,
                $voicemailBox->switch_resource_id,
                $message->switch_resource_id,
                $folder,
            );

            return DB::transaction(function () use ($account, $message, $folder, $actor, $ipAddress, $fromFolder, $snapshot): SwitchVoicemailMessage {
                $projected = $this->projection->refresh($message, $snapshot);
                $this->audit->record(
                    $actor,
                    $account,
                    'voicemail_message.folder_changed',
                    'succeeded',
                    $projected->switch_resource_id,
                    ['from' => $fromFolder, 'to' => $folder->value],
                    $ipAddress,
                    'voicemail_message',
                );

                return $projected;
            });
        } catch (Throwable $exception) {
            $this->recordFailure(
                $actor,
                $account,
                'voicemail_message.folder_change_failed',
                $message->switch_resource_id,
                ['from' => $fromFolder, 'to' => $folder->value],
                $exception,
                $ipAddress,
            );

            throw $exception;
        }
    }

    /**
     * @param  Collection<int, SwitchVoicemailMessage>  $messages
     * @return array{succeeded: list<string>, failed: list<array{id: string, reason: string}>}
     */
    public function changeFolders(
        SwitchAccount $account,
        SwitchVoicemailBox $voicemailBox,
        Collection $messages,
        VoicemailMessageFolder $folder,
        User $actor,
        ?string $ipAddress = null,
    ): array {
        $messagesByResourceId = $messages->keyBy('switch_resource_id');
        $resourceIds = $messagesByResourceId->keys()->all();

        try {
            $result = $this->gateway->changeFolders(
                $account,
                $voicemailBox->switch_resource_id,
                $resourceIds,
                $folder,
            );

            return DB::transaction(function () use ($account, $messagesByResourceId, $resourceIds, $folder, $actor, $ipAddress, $result): array {
                $succeeded = [];
                $failed = [];
                $handledResourceIds = [];

                foreach ($result['succeeded'] as $resourceId) {
                    /** @var SwitchVoicemailMessage|null $message */
                    $message = $messagesByResourceId->get($resourceId);

                    if ($message === null) {
                        continue;
                    }

                    $switchJson = $message->switch_json ?? [];
                    $switchJson['folder'] = $folder->value;
                    $message->update([
                        'folder' => $folder->value,
                        'last_synced_at' => now(),
                        'sync_status' => ProjectionStatus::Healthy,
                        'switch_json' => $switchJson,
                    ]);
                    $succeeded[] = (string) $message->id;
                    $handledResourceIds[$resourceId] = true;
                }

                foreach ($result['failed'] as $resourceId => $reason) {
                    /** @var SwitchVoicemailMessage|null $message */
                    $message = $messagesByResourceId->get($resourceId);

                    if ($message !== null) {
                        $failed[] = ['id' => (string) $message->id, 'reason' => $reason];
                        $handledResourceIds[$resourceId] = true;
                    }
                }

                foreach ($resourceIds as $resourceId) {
                    if (! isset($handledResourceIds[$resourceId])) {
                        /** @var SwitchVoicemailMessage $message */
                        $message = $messagesByResourceId->get($resourceId);
                        $failed[] = ['id' => (string) $message->id, 'reason' => 'unknown_switch_result'];
                    }
                }

                $this->audit->record(
                    $actor,
                    $account,
                    'voicemail_message.bulk_folder_changed',
                    $failed === [] ? 'succeeded' : 'partial',
                    null,
                    [
                        'folder' => $folder->value,
                        'requested_count' => count($resourceIds),
                        'succeeded_count' => count($succeeded),
                        'failed_count' => count($failed),
                    ],
                    $ipAddress,
                    'voicemail_message',
                );

                return ['succeeded' => $succeeded, 'failed' => $failed];
            });
        } catch (Throwable $exception) {
            $this->recordFailure(
                $actor,
                $account,
                'voicemail_message.bulk_folder_change_failed',
                null,
                ['folder' => $folder->value, 'requested_count' => count($resourceIds)],
                $exception,
                $ipAddress,
            );

            throw $exception;
        }
    }

    /** @param array<string, mixed> $metadata */
    private function recordFailure(
        User $actor,
        SwitchAccount $account,
        string $action,
        ?string $resourceId,
        array $metadata,
        Throwable $exception,
        ?string $ipAddress,
    ): void {
        $this->audit->record(
            $actor,
            $account,
            $action,
            'failed',
            $resourceId,
            $metadata + ['error_type' => $exception::class],
            $ipAddress,
            'voicemail_message',
        );
    }
}
