<?php

namespace App\Domains\Payments\Jobs;

use App\Domains\Payments\Enums\PaymentWebhookDeliveryStatus;
use App\Domains\Payments\Models\PaymentWebhookDelivery;
use App\Domains\Payments\Services\PaymentWebhookReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ReconcilePaymentWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 300];

    public function __construct(public readonly string $deliveryId) {}

    public function uniqueId(): string
    {
        return "payment-webhook:{$this->deliveryId}";
    }

    public function handle(PaymentWebhookReconciliationService $service): void
    {
        $service->reconcile($this->deliveryId);
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = PaymentWebhookDelivery::query()->where('id', $this->deliveryId)->first();

        if ($delivery === null || in_array($delivery->status, [
            PaymentWebhookDeliveryStatus::Processed,
            PaymentWebhookDeliveryStatus::Ignored,
        ], true)) {
            return;
        }

        $delivery->forceFill([
            'status' => PaymentWebhookDeliveryStatus::Failed,
            'safe_error_code' => 'reconciliation_exhausted',
            'processed_at' => now(),
        ])->save();
    }
}
