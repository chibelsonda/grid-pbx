<?php

namespace App\Domains\Groups\Resources;

use App\Domains\Groups\Models\SwitchGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchGroup */
class GroupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'member_count' => $this->whenCounted('members'),
            'music_on_hold_media' => $this->whenLoaded('musicOnHoldMedia', fn (): ?array => $this->musicOnHoldMedia === null ? null : ['id' => $this->musicOnHoldMedia->id, 'name' => $this->musicOnHoldMedia->name]),
            'members' => $this->whenLoaded('members', fn (): array => $this->members->sortBy('weight')->map(function ($member): array {
                $target = match ($member->member_type) {
                    'user' => $member->extension,
                    'device' => $member->device,
                    'group' => $member->nestedGroup,
                    default => null,
                };

                return [
                    'id' => $member->id, 'type' => $member->member_type, 'weight' => $member->weight,
                    'target' => $target === null ? null : [
                        'id' => $target->id,
                        'label' => $target->display_name ?? $target->name ?? $target->extension ?? 'Unnamed target',
                        'detail' => $target->extension ?? $target->device_type ?? null,
                    ],
                    'resolved' => $target !== null,
                ];
            })->values()->all()),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value,
            'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
