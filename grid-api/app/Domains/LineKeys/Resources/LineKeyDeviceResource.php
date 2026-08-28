<?php

namespace App\Domains\LineKeys\Resources;

use App\Domains\Devices\Models\SwitchDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchDevice */
class LineKeyDeviceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'make' => $this->make,
            'endpoint_family' => $this->endpoint_family,
            'model' => $this->model,
            'mac_address' => $this->mac_address,
            'line_keys' => LineKeyResource::collection($this->whenLoaded('lineKeys')),
        ];
    }
}
