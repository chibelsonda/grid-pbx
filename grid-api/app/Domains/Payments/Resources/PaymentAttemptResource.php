<?php

namespace App\Domains\Payments\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAttemptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'source_attempt_id' => $this->resource->sourceAttempt?->id,
            'provider' => $this->resource->provider,
            'operation' => $this->resource->operation->value,
            'amount' => $this->resource->amount,
            'currency' => $this->resource->currency,
            'status' => $this->resource->status->value,
            'safe_error_code' => $this->resource->safe_error_code,
            'provider_status' => $this->resource->provider_status,
            'reconciled_at' => $this->resource->reconciled_at?->toIso8601String(),
            'completed_at' => $this->resource->completed_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
