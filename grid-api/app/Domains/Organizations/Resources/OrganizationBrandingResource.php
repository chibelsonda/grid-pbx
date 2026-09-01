<?php

namespace App\Domains\Organizations\Resources;

use App\Domains\Organizations\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class OrganizationBrandingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'organization_id' => $this->id,
            'logo_available' => filled($this->logo_path),
            'logo_updated_at' => $this->logo_updated_at?->toIso8601String(),
        ];
    }
}
