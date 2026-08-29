<?php

namespace App\Domains\Voicemail\Resources;

use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchVoicemailBox */
class VoicemailBoxResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mailbox' => $this->mailbox,
            'timezone' => $this->timezone,
            'notification_emails' => $this->notification_emails ?? [],
            'transcribe' => $this->transcribe,
            'require_pin' => $this->require_pin,
            'pin_configured' => $this->pinConfigured(),
            'is_setup' => $this->is_setup,
            'configuration' => [
                'check_if_owner' => $this->safeBoolean('check_if_owner', true),
                'delete_after_notify' => $this->safeBoolean('delete_after_notify', false),
                'include_message_on_notify' => $this->safeBoolean('include_message_on_notify', true),
                'include_transcription_on_notify' => $this->safeBoolean('include_transcription_on_notify', true),
                'media_extension' => $this->safeMediaExtension(),
                'not_configurable' => $this->safeBoolean('not_configurable', false),
                'oldest_message_first' => $this->safeBoolean('oldest_message_first', false),
                'save_after_notify' => $this->safeBoolean('save_after_notify', false),
                'skip_envelope' => $this->safeBoolean('skip_envelope', false),
                'skip_greeting' => $this->safeBoolean('skip_greeting', false),
                'skip_instructions' => $this->safeBoolean('skip_instructions', false),
                'is_voicemail_ff_rw_enabled' => $this->safeBoolean('is_voicemail_ff_rw_enabled', false),
                'seek_duration_ms' => $this->safeInteger('seek_duration_ms', 10000),
                'notify_callback' => $this->safeNotificationCallback(),
            ],
            'message_counts' => [
                'total' => (int) $this->messages_count,
                'new' => (int) $this->new_messages_count,
                'saved' => (int) $this->saved_messages_count,
                'deleted' => (int) $this->deleted_messages_count,
            ],
            'unavailable_greeting' => $this->unavailableGreeting === null
                ? null
                : new VoicemailGreetingResource($this->unavailableGreeting),
            'assigned_extension' => $this->extension === null ? null : [
                'id' => $this->extension->id,
                'display_name' => $this->extension->display_name,
                'extension' => $this->extension->extension,
            ],
            'sync_status' => $this->sync_status->value,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }

    private function safeBoolean(string $key, bool $default): bool
    {
        $value = $this->switch_json[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    private function safeInteger(string $key, int $default): int
    {
        $value = $this->switch_json[$key] ?? null;

        return is_int($value) ? $value : $default;
    }

    private function safeMediaExtension(): string
    {
        $value = $this->switch_json['media_extension'] ?? null;

        return in_array($value, ['mp3', 'mp4', 'wav'], true) ? $value : 'mp3';
    }

    /** @return array<string, mixed>|null */
    private function safeNotificationCallback(): ?array
    {
        $callback = $this->switch_json['notify']['callback'] ?? null;

        if (! is_array($callback)) {
            return null;
        }

        return [
            'disabled' => is_bool($callback['disabled'] ?? null) ? $callback['disabled'] : false,
            'number' => is_string($callback['number'] ?? null) ? $callback['number'] : null,
            'attempts' => is_int($callback['attempts'] ?? null) ? $callback['attempts'] : null,
            'interval_s' => is_int($callback['interval_s'] ?? null) ? $callback['interval_s'] : null,
            'timeout_s' => is_int($callback['timeout_s'] ?? null) ? $callback['timeout_s'] : null,
            'schedule' => array_values(array_filter(
                is_array($callback['schedule'] ?? null) ? $callback['schedule'] : [],
                static fn (mixed $interval): bool => is_int($interval) && $interval >= 0,
            )),
        ];
    }
}
