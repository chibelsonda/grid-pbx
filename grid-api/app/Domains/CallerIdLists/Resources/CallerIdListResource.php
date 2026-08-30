<?php

namespace App\Domains\CallerIdLists\Resources;

use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchCallerIdList */
class CallerIdListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'organization' => $this->organization,
            'entry_count' => $this->whenCounted('entries'),
            'entries' => $this->whenLoaded('entries', fn () => $this->entries->map(fn ($entry): array => [
                'id' => $entry->id,
                'display_name' => $entry->display_name,
                'number' => $entry->number,
                'pattern' => $entry->pattern,
            ])->values()->all()),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'sync_status' => $this->sync_status?->value,
        ];
    }
}
