<?php

namespace App\Domains\Payments\Resources;

use App\Domains\Payments\Enums\PaymentWebhookDeliveryStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentWebhookDeliveryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'payment_attempt_id' => $this->resource->paymentAttempt?->id,
            'provider' => $this->resource->provider,
            'event_type' => $this->resource->event_type,
            'status' => $this->resource->status->value,
            'processing_attempts' => $this->resource->processing_attempts,
            'safe_error_code' => $this->resource->safe_error_code,
            'can_retry' => $this->canRetry(),
            'recovery_guidance' => $this->recoveryGuidance(),
            'event_occurred_at' => $this->resource->event_occurred_at?->toIso8601String(),
            'received_at' => $this->resource->received_at?->toIso8601String(),
            'processed_at' => $this->resource->processed_at?->toIso8601String(),
        ];
    }

    private function canRetry(): bool
    {
        return $this->resource->status === PaymentWebhookDeliveryStatus::Failed
            && $this->resource->processing_attempts < 10;
    }

    private function recoveryGuidance(): string
    {
        return match ($this->resource->status) {
            PaymentWebhookDeliveryStatus::Received => 'Reconciliation is queued.',
            PaymentWebhookDeliveryStatus::Processing => 'Provider status is being verified.',
            PaymentWebhookDeliveryStatus::Processed => 'The current provider state was confirmed.',
            PaymentWebhookDeliveryStatus::Ignored => 'No supported GridPBX transaction required reconciliation.',
            PaymentWebhookDeliveryStatus::RetryPending => 'An automatic retry is scheduled.',
            PaymentWebhookDeliveryStatus::Failed => $this->canRetry()
                ? 'Automatic retries were exhausted. Verify provider connectivity, then retry.'
                : 'The recovery limit was reached. Verify configuration before further action.',
        };
    }
}
