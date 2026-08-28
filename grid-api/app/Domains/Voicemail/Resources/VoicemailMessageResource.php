<?php

namespace App\Domains\Voicemail\Resources;

use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchVoicemailMessage */
class VoicemailMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folder' => $this->folder,
            'caller_id_name' => $this->caller_id_name,
            'caller_id_number' => $this->caller_id_number,
            'from_address' => $this->from_address,
            'to_address' => $this->to_address,
            'length' => $this->length,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'transcription_result' => $this->transcription_result,
            'transcription_text' => $this->transcription_text,
            'sync_status' => $this->sync_status->value,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
