<?php

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Contracts\PaymentTransactionStatusGateway;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Enums\PaymentWebhookDeliveryStatus;
use App\Domains\Payments\Exceptions\PaymentWebhookRetryException;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Models\PaymentWebhookDelivery;
use Illuminate\Support\Facades\DB;

final class PaymentWebhookReconciliationService
{
    public function __construct(
        private readonly PaymentTransactionStatusGateway $gateway,
        private readonly PaymentWebhookEventPolicy $events,
    ) {}

    public function reconcile(string $deliveryId): void
    {
        $delivery = PaymentWebhookDelivery::query()->where('id', $deliveryId)->firstOrFail();

        if (in_array($delivery->status, [
            PaymentWebhookDeliveryStatus::Processed,
            PaymentWebhookDeliveryStatus::Ignored,
        ], true)) {
            return;
        }

        $delivery->forceFill([
            'status' => PaymentWebhookDeliveryStatus::Processing,
            'processing_attempts' => $delivery->processing_attempts + 1,
            'safe_error_code' => null,
        ])->save();

        $operation = $this->events->operation($delivery->event_type);
        $providerReference = $delivery->provider_reference;

        if ($operation === null || ! is_string($providerReference) || $providerReference === '') {
            $this->ignore($delivery, 'unsupported_event');

            return;
        }

        $attempt = $this->findAttempt($delivery, $operation);

        if ($attempt === null) {
            $this->ignore($delivery, 'unmatched_transaction');

            return;
        }

        $result = $this->gateway->status($providerReference, $operation);

        if ($result->retryable) {
            $delivery->forceFill([
                'payment_attempt_id' => $attempt->getKey(),
                'status' => PaymentWebhookDeliveryStatus::RetryPending,
                'safe_error_code' => $result->safeErrorCode,
            ])->save();

            throw new PaymentWebhookRetryException;
        }

        DB::transaction(function () use (
            $delivery,
            $attempt,
            $providerReference,
            $result,
        ): void {
            $lockedAttempt = PaymentAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->getKey());
            $lockedDelivery = PaymentWebhookDelivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->getKey());

            $nextStatus = $this->nextAttemptStatus(
                $lockedAttempt->status,
                $result->attemptStatus,
            );
            $providerReferenceHash = $this->secureHash($providerReference);

            $lockedAttempt->forceFill([
                'status' => $nextStatus,
                'provider_reference' => $lockedAttempt->provider_reference ?? $providerReference,
                'provider_reference_hash' => $lockedAttempt->provider_reference_hash
                    ?? $providerReferenceHash,
                'safe_error_code' => $nextStatus === PaymentAttemptStatus::Succeeded
                    ? null
                    : $result->safeErrorCode,
                'provider_status' => $result->providerStatus,
                'reconciled_at' => now(),
                'completed_at' => $lockedAttempt->completed_at ?? now(),
            ])->save();

            $lockedAttempt->events()->create([
                'event_type' => 'webhook_reconciled',
                'status' => $nextStatus,
                'provider_reference_hash' => $providerReferenceHash,
                'safe_context' => [
                    'delivery_id' => $lockedDelivery->id,
                    'event_type' => $lockedDelivery->event_type,
                    'provider_status' => $result->providerStatus,
                ],
            ]);

            $lockedDelivery->forceFill([
                'payment_attempt_id' => $lockedAttempt->getKey(),
                'status' => PaymentWebhookDeliveryStatus::Processed,
                'safe_error_code' => $result->safeErrorCode,
                'processed_at' => now(),
            ])->save();
        });
    }

    private function findAttempt(
        PaymentWebhookDelivery $delivery,
        PaymentOperation $operation,
    ): ?PaymentAttempt {
        $attempt = PaymentAttempt::query()
            ->where('provider', $delivery->provider)
            ->where('operation', $operation)
            ->where('provider_reference_hash', $delivery->provider_reference_hash)
            ->latest('payment_attempt_id')
            ->first();

        if ($attempt !== null || blank($delivery->merchant_reference)) {
            return $attempt;
        }

        $merchantReference = (string) $delivery->merchant_reference;

        if (! preg_match('/^[0-9a-f-]{8,36}$/i', $merchantReference)) {
            return null;
        }

        $matches = PaymentAttempt::query()
            ->where('provider', $delivery->provider)
            ->where('operation', $operation)
            ->where('id', 'like', $merchantReference.'%')
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function nextAttemptStatus(
        PaymentAttemptStatus $current,
        PaymentAttemptStatus $authoritative,
    ): PaymentAttemptStatus {
        if (! in_array($current, [
            PaymentAttemptStatus::Pending,
            PaymentAttemptStatus::Indeterminate,
        ], true)) {
            return $current;
        }

        return $authoritative;
    }

    private function ignore(PaymentWebhookDelivery $delivery, string $safeErrorCode): void
    {
        $delivery->forceFill([
            'status' => PaymentWebhookDeliveryStatus::Ignored,
            'safe_error_code' => $safeErrorCode,
            'processed_at' => now(),
        ])->save();
    }

    private function secureHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
