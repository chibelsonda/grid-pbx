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
            'is_setup' => $this->is_setup,
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
}
