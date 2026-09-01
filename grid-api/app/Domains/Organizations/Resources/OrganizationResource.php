<?php

namespace App\Domains\Organizations\Resources;

use App\Domains\Organizations\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class OrganizationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'branding' => [
                'logo_available' => filled($this->logo_path),
                'logo_updated_at' => $this->logo_updated_at?->toIso8601String(),
            ],
        ];
    }
}
