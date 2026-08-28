<?php

namespace App\Domains\Media\Resources;

use App\Domains\Media\Models\SwitchMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchMedia */
class MediaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'language' => $this->language,
            'media_source' => $this->media_source,
            'content_type' => $this->content_type,
            'content_length' => $this->content_length,
            'prompt_id' => $this->prompt_id,
            'streamable' => $this->streamable,
            'is_music_on_hold' => (bool) ($this->is_music_on_hold ?? false),
            'dependencies' => $this->when(
                isset($this->dependency_summary),
                fn (): array => $this->dependency_summary,
            ),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'sync_status' => $this->sync_status?->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
