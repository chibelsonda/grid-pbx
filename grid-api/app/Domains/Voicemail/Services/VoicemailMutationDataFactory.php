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
        $snapshot = is_array($existingVoicemailBox?->switch_json)
            ? $existingVoicemailBox->switch_json
            : [];
        $notify = is_array($snapshot['notify'] ?? null) ? $snapshot['notify'] : [];
        $callback = is_array($notify['callback'] ?? null) ? $notify['callback'] : [];
        $notificationCallback = $data['notify_callback'] ?? null;

        if (is_array($notificationCallback)) {
            $notificationCallback['preserved_options'] = $this->safePreservedOptions(
                $callback,
                ['disabled', 'number', 'attempts', 'interval_s', 'timeout_s', 'schedule'],
            );
        }

        return [
            'name' => $data['name'],
            'mailbox' => $data['mailbox'],
            'owner_switch_resource_id' => $ownerSwitchResourceId ?? $extension?->switch_resource_id,
            'timezone' => $data['timezone'] ?? null,
            'notification_emails' => array_values($data['notification_emails']),
            'transcribe' => $data['transcribe'],
            'require_pin' => $data['require_pin'],
            'pin' => $data['pin'] ?? null,
            'preserve_pin' => $existingVoicemailBox?->pinConfigured() === true
                && ! isset($data['pin']),
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
            'flags' => $this->stringList($snapshot['flags'] ?? null),
            'notify_callback' => $notificationCallback,
            'advanced_preserved_options' => $this->safePreservedOptions($snapshot, [
                'id',
                'name',
                'mailbox',
                'owner_id',
                'timezone',
                'notify_email_addresses',
                'transcribe',
                'require_pin',
                'pin',
                'check_if_owner',
                'delete_after_notify',
                'include_message_on_notify',
                'include_transcription_on_notify',
                'media_extension',
                'not_configurable',
                'oldest_message_first',
                'save_after_notify',
                'skip_envelope',
                'skip_greeting',
                'skip_instructions',
                'is_voicemail_ff_rw_enabled',
                'seek_duration_ms',
                'flags',
                'notify',
            ]),
            'notify_preserved_options' => $this->safePreservedOptions($notify, ['callback']),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $editableKeys
     * @return array<string, mixed>
     */
    private function safePreservedOptions(array $source, array $editableKeys): array
    {
        $preserved = $this->withoutRedactedValues(
            array_diff_key($source, array_flip($editableKeys)),
        );

        return is_array($preserved) ? $preserved : [];
    }

    private function withoutRedactedValues(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value === '[REDACTED]' ? null : $value;
        }

        $clean = [];

        foreach ($value as $key => $item) {
            $item = $this->withoutRedactedValues($item);

            if ($item !== null) {
                $clean[$key] = $item;
            }
        }

        return $clean;
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
