<?php

namespace App\Domains\Recordings\Resources;

use App\Domains\Recordings\Models\SwitchRecording;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchRecording */
class RecordingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'call_id' => $this->call_id, 'interaction_id' => $this->interaction_id, 'direction' => $this->direction, 'caller' => ['name' => $this->caller_id_name, 'number' => $this->caller_id_number], 'callee' => ['name' => $this->callee_id_name, 'number' => $this->callee_id_number], 'from' => $this->from_uri, 'to' => $this->to_uri, 'request' => $this->request_uri, 'started_at' => $this->started_at->toIso8601String(), 'duration_seconds' => $this->duration_seconds, 'duration_milliseconds' => $this->duration_milliseconds, 'name' => $this->name, 'description' => $this->description, 'content_type' => $this->content_type, 'content_length' => $this->content_length, 'media_source' => $this->media_source, 'media_type' => $this->media_type, 'source_type' => $this->source_type, 'origin' => $this->origin, 'has_audio' => $this->has_audio, 'extension' => $this->extension === null ? null : ['id' => $this->extension->id, 'display_name' => $this->extension->display_name, 'extension' => $this->extension->extension], 'call_detail_record_id' => $this->callDetailRecord?->id, 'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value];
    }
}
