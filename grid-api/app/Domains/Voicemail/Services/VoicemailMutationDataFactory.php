<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;

class VoicemailMutationDataFactory
{
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function make(
        SwitchAccount $account,
        array $data,
        ?string $ownerSwitchResourceId = null,
        ?SwitchVoicemailBox $existingVoicemailBox = null,
    ): array {
        $extension = $ownerSwitchResourceId === null && isset($data['assigned_extension_id'])
            ? $account->extensions()->where('id', $data['assigned_extension_id'])->firstOrFail()
            : null;

        return [
            'name' => $data['name'],
            'mailbox' => $data['mailbox'],
            'owner_switch_resource_id' => $ownerSwitchResourceId ?? $extension?->switch_resource_id,
            'timezone' => $data['timezone'] ?? null,
            'notification_emails' => array_values($data['notification_emails']),
            'transcribe' => $data['transcribe'],
            'require_pin' => $data['require_pin'],
            'pin' => $data['pin'] ?? null,
            'check_if_owner' => $data['check_if_owner'] ?? null,
            'delete_after_notify' => $data['delete_after_notify'] ?? null,
            'include_message_on_notify' => $data['include_message_on_notify'] ?? null,
            'include_transcription_on_notify' => $data['include_transcription_on_notify'] ?? null,
            'media_extension' => $data['media_extension'] ?? null,
            'not_configurable' => $data['not_configurable'] ?? null,
            'oldest_message_first' => $data['oldest_message_first'] ?? null,
            'save_after_notify' => $data['save_after_notify'] ?? null,
            'skip_envelope' => $data['skip_envelope'] ?? null,
            'skip_greeting' => $data['skip_greeting'] ?? null,
            'skip_instructions' => $data['skip_instructions'] ?? null,
            'is_voicemail_ff_rw_enabled' => $data['is_voicemail_ff_rw_enabled'] ?? null,
            'seek_duration_ms' => $data['seek_duration_ms'] ?? null,
            'flags' => $this->stringList(
                $existingVoicemailBox === null
                    ? null
                    : ($existingVoicemailBox->switch_json['flags'] ?? null),
            ),
            'notify_callback' => $data['notify_callback'] ?? null,
        ];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(
            is_array($value) ? $value : [],
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }
}
