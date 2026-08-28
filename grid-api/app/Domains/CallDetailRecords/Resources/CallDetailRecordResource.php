<?php

namespace App\Domains\CallDetailRecords\Resources;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchCallDetailRecord */
class CallDetailRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'call_id' => $this->call_id,
            'interaction_id' => $this->interaction_id,
            'direction' => $this->direction,
            'caller' => [
                'name' => $this->caller_id_name,
                'number' => $this->caller_id_number,
            ],
            'callee' => [
                'name' => $this->callee_id_name,
                'number' => $this->callee_id_number,
            ],
            'from' => $this->from_uri,
            'to' => $this->to_uri,
            'request' => $this->request_uri,
            'started_at' => $this->started_at->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'billing_seconds' => $this->billing_seconds,
            'answered' => $this->billing_seconds > 0,
            'hangup_cause' => $this->hangup_cause,
            'disposition' => $this->disposition,
            'recording_available' => $this->recording_available,
            'extension' => $this->extension === null ? null : [
                'id' => $this->extension->id,
                'display_name' => $this->extension->display_name,
                'extension' => $this->extension->extension,
            ],
            'last_synced_at' => $this->last_synced_at->toIso8601String(),
        ];
    }
}
