<?php

namespace App\Domains\Voicemail\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Contracts\SwitchVoicemailBoxGateway;
use GridPbx\Switch\Domains\Voicemail\Dto\VoicemailBoxAdvancedData;
use GridPbx\Switch\Domains\Voicemail\Dto\VoicemailBoxWriteData;
use GridPbx\Switch\Domains\Voicemail\Dto\VoicemailNotificationCallbackData;
use GridPbx\Switch\Domains\Voicemail\VoicemailBoxResourceClient;

class CrossbarSwitchVoicemailBoxGateway implements SwitchVoicemailBoxGateway
{
    public function __construct(private readonly VoicemailBoxResourceClient $voicemailBoxes) {}

    public function create(SwitchAccount $account, array $voicemailBox): array
    {
        return $this->voicemailBoxes
            ->create($account->switch_account_id, $this->writeData($voicemailBox))
            ->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $voicemailBox): array
    {
        return $this->voicemailBoxes
            ->update($account->switch_account_id, $resourceId, $this->writeData($voicemailBox))
            ->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->voicemailBoxes->delete($account->switch_account_id, $resourceId);
    }

    /** @param array<string, mixed> $voicemailBox */
    private function writeData(array $voicemailBox): VoicemailBoxWriteData
    {
        return new VoicemailBoxWriteData(
            name: $voicemailBox['name'],
            mailbox: $voicemailBox['mailbox'],
            ownerId: $voicemailBox['owner_switch_resource_id'],
            timezone: $voicemailBox['timezone'],
            notificationEmails: $voicemailBox['notification_emails'],
            transcribe: $voicemailBox['transcribe'],
            requirePin: $voicemailBox['require_pin'],
            pin: $voicemailBox['pin'],
            advanced: new VoicemailBoxAdvancedData(
                checkIfOwner: $voicemailBox['check_if_owner'],
                deleteAfterNotify: $voicemailBox['delete_after_notify'],
                includeMessageOnNotify: $voicemailBox['include_message_on_notify'],
                includeTranscriptionOnNotify: $voicemailBox['include_transcription_on_notify'],
                mediaExtension: $voicemailBox['media_extension'],
                notConfigurable: $voicemailBox['not_configurable'],
                oldestMessageFirst: $voicemailBox['oldest_message_first'],
                saveAfterNotify: $voicemailBox['save_after_notify'],
                skipEnvelope: $voicemailBox['skip_envelope'],
                skipGreeting: $voicemailBox['skip_greeting'],
                skipInstructions: $voicemailBox['skip_instructions'],
                fastForwardRewindEnabled: $voicemailBox['is_voicemail_ff_rw_enabled'],
                seekDurationMilliseconds: $voicemailBox['seek_duration_ms'],
                flags: $voicemailBox['flags'],
                notificationCallback: $this->notificationCallback($voicemailBox['notify_callback']),
            ),
        );
    }

    /** @param array<string, mixed>|null $callback */
    private function notificationCallback(?array $callback): ?VoicemailNotificationCallbackData
    {
        if ($callback === null) {
            return null;
        }

        return new VoicemailNotificationCallbackData(
            disabled: $callback['disabled'],
            number: $callback['number'] ?? null,
            attempts: $callback['attempts'] ?? null,
            intervalSeconds: $callback['interval_s'] ?? null,
            timeoutSeconds: $callback['timeout_s'] ?? null,
            schedule: $callback['schedule'] ?? [],
        );
    }
}
