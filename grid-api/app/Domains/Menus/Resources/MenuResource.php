<?php

namespace App\Domains\Menus\Resources;

use App\Domains\Menus\Models\SwitchMenu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchMenu */
class MenuResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $media = static fn ($item): ?array => $item === null ? null : ['id' => $item->id, 'name' => $item->name];

        return [
            'id' => $this->id, 'name' => $this->name, 'timeout' => $this->timeout,
            'interdigit_timeout' => $this->interdigit_timeout, 'max_extension_length' => $this->max_extension_length,
            'retries' => $this->retries, 'hunt' => $this->hunt,
            'allow_record_from_offnet' => $this->allow_record_from_offnet, 'suppress_media' => $this->suppress_media,
            'record_pin_configured' => $this->record_pin_configured, 'hunt_allow' => $this->hunt_allow, 'hunt_deny' => $this->hunt_deny,
            'greeting_media' => $this->whenLoaded('greetingMedia', fn () => $media($this->greetingMedia)),
            'greeting_media_unresolved' => $this->greeting_media_reference !== null && $this->greeting_media_id === null,
            'invalid_media_enabled' => $this->invalid_media_enabled,
            'invalid_media' => $this->whenLoaded('invalidMedia', fn () => $media($this->invalidMedia)),
            'invalid_media_unresolved' => $this->invalid_media_reference !== null && $this->invalid_media_id === null,
            'transfer_media_enabled' => $this->transfer_media_enabled,
            'transfer_media' => $this->whenLoaded('transferMedia', fn () => $media($this->transferMedia)),
            'transfer_media_unresolved' => $this->transfer_media_reference !== null && $this->transfer_media_id === null,
            'exit_media_enabled' => $this->exit_media_enabled,
            'exit_media' => $this->whenLoaded('exitMedia', fn () => $media($this->exitMedia)),
            'exit_media_unresolved' => $this->exit_media_reference !== null && $this->exit_media_id === null,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value,
            'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
