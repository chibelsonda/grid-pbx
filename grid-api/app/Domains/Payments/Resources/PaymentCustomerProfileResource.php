<?php

namespace App\Domains\Payments\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentCustomerProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'provider' => $this->resource->provider,
            'status' => $this->resource->status,
            'masked_account' => $this->resource->masked_account,
            'account_type' => $this->resource->account_type,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
