<?php

namespace App\Domains\Payments\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAttemptEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $context = is_array($this->resource->safe_context)
            ? $this->resource->safe_context
            : [];

        return [
            'id' => $this->resource->id,
            'event_type' => $this->resource->event_type,
            'status' => $this->resource->status?->value,
            'summary' => $this->summary(),
            'safe_error_code' => $this->safeCode($context['safe_error_code'] ?? null),
            'provider_status' => $this->safeCode($context['provider_status'] ?? null),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }

    private function summary(): string
    {
        return match ($this->resource->event_type) {
            'attempt_created' => 'GridPBX accepted the operation.',
            'provider_result_recorded' => 'The provider result was recorded.',
            'webhook_reconciled' => 'The provider status was reconciled from a signed webhook.',
            'webhook_retry_requested' => 'An administrator requested reconciliation recovery.',
            default => 'A payment state transition was recorded.',
        };
    }

    private function safeCode(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^[a-z0-9_]{1,64}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
