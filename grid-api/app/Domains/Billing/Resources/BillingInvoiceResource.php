<?php

namespace App\Domains\Billing\Resources;

use App\Domains\Billing\Dto\BillingInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BillingInvoice */
final class BillingInvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
