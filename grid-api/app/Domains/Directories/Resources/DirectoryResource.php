<?php

namespace App\Domains\Directories\Resources;

use App\Domains\Directories\Models\SwitchDirectory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchDirectory */
class DirectoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'confirm_match' => $this->confirm_match,
            'min_dtmf' => $this->min_dtmf, 'max_dtmf' => $this->max_dtmf, 'sort_by' => $this->sort_by,
            'member_count' => $this->whenCounted('members'),
            'members' => $this->whenLoaded('members', fn (): array => $this->members->map(fn ($member): array => [
                'id' => $member->id,
                'extension' => $member->extension === null ? null : [
                    'id' => $member->extension->id,
                    'label' => $member->extension->display_name ?? $member->extension->extension ?? 'Unnamed extension',
                    'number' => $member->extension->extension,
                ],
                'callflow' => $member->callflow === null ? null : ['id' => $member->callflow->id, 'name' => $member->callflow->name],
                'resolved' => $member->extension !== null && $member->callflow !== null,
            ])->values()->all()),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value,
            'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
