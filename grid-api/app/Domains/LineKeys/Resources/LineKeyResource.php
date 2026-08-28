<?php

namespace App\Domains\LineKeys\Resources;

use App\Domains\LineKeys\Models\SwitchLineKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchLineKey */
class LineKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'position' => $this->position,
            'type' => $this->type,
            'label' => $this->label,
            'value' => $this->value,
        ];
    }
}
