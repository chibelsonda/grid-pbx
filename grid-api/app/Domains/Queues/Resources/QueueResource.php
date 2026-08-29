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
        $announcements = is_array($this->switch_json['announcements'] ?? null) ? $this->switch_json['announcements'] : [];
        $announcementMedia = is_array($announcements['media'] ?? null) ? $announcements['media'] : [];

        return [
            'id' => $this->id, 'name' => $this->name, 'strategy' => $this->strategy,
            'agent_count' => $this->whenCounted('agents'),
            'agent_ring_timeout' => $this->agent_ring_timeout, 'agent_wrapup_time' => $this->agent_wrapup_time,
            'connection_timeout' => $this->connection_timeout, 'max_queue_size' => $this->max_queue_size,
            'ring_simultaneously' => $this->ring_simultaneously, 'enter_when_empty' => $this->enter_when_empty,
            'record_caller' => $this->record_caller, 'caller_exit_key' => $this->caller_exit_key,
            'music_on_hold_media' => $this->whenLoaded('musicOnHoldMedia', fn (): ?array => $this->musicOnHoldMedia === null ? null : ['id' => $this->musicOnHoldMedia->id, 'name' => $this->musicOnHoldMedia->name]),
            'announce_media' => $this->mediaResource($this->switch_json['announce'] ?? null),
            'max_priority' => is_int($this->switch_json['max_priority'] ?? null) ? $this->switch_json['max_priority'] : null,
            'announcements' => [
                'enabled' => $announcements !== [],
                'interval' => max(15, min(86400, (int) ($announcements['interval'] ?? 30))),
                'position_announcements_enabled' => (bool) ($announcements['position_announcements_enabled'] ?? false),
                'wait_time_announcements_enabled' => (bool) ($announcements['wait_time_announcements_enabled'] ?? false),
                'media' => [
                    'in_the_queue' => $this->mediaResource($announcementMedia['in_the_queue'] ?? null),
                    'increase_in_call_volume' => $this->mediaResource($announcementMedia['increase_in_call_volume'] ?? null),
                    'the_estimated_wait_time_is' => $this->mediaResource($announcementMedia['the_estimated_wait_time_is'] ?? null),
                    'you_are_at_position' => $this->mediaResource($announcementMedia['you_are_at_position'] ?? null),
                ],
            ],
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

    /** @return array{id: string, name: string}|null */
    private function mediaResource(mixed $reference): ?array
    {
        if (! is_string($reference) || $reference === '' || ! $this->relationLoaded('switchAccount') || ! $this->switchAccount->relationLoaded('media')) {
            return null;
        }

        $media = $this->switchAccount->media->firstWhere('switch_resource_id', $reference);

        return $media === null ? null : ['id' => $media->id, 'name' => $media->name];
    }
}
