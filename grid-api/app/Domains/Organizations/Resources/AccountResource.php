<?php

namespace App\Domains\Organizations\Resources;

use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchAccount */
class AccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'realm' => $this->realm,
            'organization' => [
                'id' => $this->organization_id,
                'name' => $this->whenLoaded('organization', fn () => $this->organization->name),
            ],
        ];
    }
}
