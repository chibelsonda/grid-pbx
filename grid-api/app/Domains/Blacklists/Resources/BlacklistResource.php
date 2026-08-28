<?php

namespace App\Domains\Blacklists\Resources;

use App\Domains\Blacklists\Models\SwitchBlacklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchBlacklist */
class BlacklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'should_block_anonymous' => $this->should_block_anonymous, 'is_active' => $this->is_active, 'number_count' => $this->whenCounted('entries'), 'numbers' => $this->whenLoaded('entries', fn () => $this->entries->map(fn ($entry) => ['id' => $entry->id, 'number' => $entry->number])->values()->all()), 'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value];
    }
}
