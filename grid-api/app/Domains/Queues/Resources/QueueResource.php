<?php

namespace App\Domains\Queues\Resources;

use App\Domains\Queues\Models\SwitchQueue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchQueue */
class QueueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'strategy' => $this->strategy,
            'agent_count' => $this->whenCounted('agents'),
            'agent_ring_timeout' => $this->agent_ring_timeout, 'agent_wrapup_time' => $this->agent_wrapup_time,
            'connection_timeout' => $this->connection_timeout, 'max_queue_size' => $this->max_queue_size,
            'ring_simultaneously' => $this->ring_simultaneously, 'enter_when_empty' => $this->enter_when_empty,
            'record_caller' => $this->record_caller, 'caller_exit_key' => $this->caller_exit_key,
            'music_on_hold_media' => $this->whenLoaded('musicOnHoldMedia', fn (): ?array => $this->musicOnHoldMedia === null ? null : ['id' => $this->musicOnHoldMedia->id, 'name' => $this->musicOnHoldMedia->name]),
            'agents' => $this->whenLoaded('agents', fn (): array => $this->agents->map(fn ($agent): array => [
                'id' => $agent->id,
                'agent' => $agent->extension === null ? null : [
                    'id' => $agent->extension->id,
                    'name' => $agent->extension->display_name ?? $agent->extension->extension ?? 'Unnamed agent',
                    'extension' => $agent->extension->extension,
                ],
                'resolved' => $agent->extension !== null,
            ])->values()->all()),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value,
            'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
