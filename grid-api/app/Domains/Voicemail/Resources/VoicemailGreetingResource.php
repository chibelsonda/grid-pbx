<?php

namespace App\Domains\Voicemail\Resources;

use App\Domains\Voicemail\Models\SwitchVoicemailGreeting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchVoicemailGreeting */
class VoicemailGreetingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'content_type' => $this->content_type,
            'content_length' => $this->content_length,
            'media_source' => $this->media_source,
            'streamable' => $this->streamable,
            'sync_status' => $this->sync_status->value,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
