<?php

namespace App\Domains\Billing\Resources;

use App\Domains\Billing\Dto\BillingReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BillingReceipt */
final class BillingReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
