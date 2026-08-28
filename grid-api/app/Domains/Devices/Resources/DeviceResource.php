<?php

namespace App\Domains\Devices\Resources;

use App\Domains\Devices\Models\SwitchDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchDevice */
class DeviceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'device_type' => $this->device_type,
            'make' => $this->make,
            'model' => $this->model,
            'mac_address' => $this->mac_address,
            'is_enabled' => $this->is_enabled,
            'registration_status' => $this->registration_status->value,
            'registration_checked_at' => $this->registration_checked_at?->toIso8601String(),
            'assigned_extension' => $this->extension === null ? null : [
                'id' => $this->extension->id,
                'display_name' => $this->extension->display_name,
                'extension' => $this->extension->extension,
            ],
            'sync_status' => $this->sync_status->value,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
