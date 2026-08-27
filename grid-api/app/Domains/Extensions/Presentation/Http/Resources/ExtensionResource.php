<?php

namespace App\Domains\Extensions\Presentation\Http\Resources;

use App\Domains\Extensions\Infrastructure\Models\KazooExtension;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KazooExtension */
class ExtensionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'extension' => $this->extension,
            'timezone' => $this->timezone,
            'is_enabled' => $this->is_enabled,
            'sync_status' => $this->sync_status->value,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
