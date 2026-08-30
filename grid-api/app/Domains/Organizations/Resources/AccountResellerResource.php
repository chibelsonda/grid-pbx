<?php

namespace App\Domains\Organizations\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResellerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'account' => $this->resource['account'],
            'billing_reseller' => $this->resource['billing_reseller'],
            'billing_reseller_projected' => $this->resource['billing_reseller_projected'],
            'service_projection_last_synced_at' => $this->resource['service_projection_last_synced_at'],
            'mutations' => $this->resource['mutations'],
        ];
    }
}
