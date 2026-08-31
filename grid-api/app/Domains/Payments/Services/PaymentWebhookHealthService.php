<?php

namespace App\Domains\Payments\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Enums\PaymentWebhookDeliveryStatus;
use App\Domains\Payments\Models\PaymentWebhookDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class PaymentWebhookHealthService
{
    public function __construct(private readonly PaymentWebhookRecoveryService $recovery) {}

    /**
     * @return array{
     *     summary: array<string, int>,
     *     recovery_available: bool,
     *     deliveries: Collection<int, PaymentWebhookDelivery>
     * }
     */
    public function get(SwitchAccount $account, int $limit = 25): array
    {
        $scope = PaymentWebhookDelivery::query()
            ->whereHas('paymentAttempt', function (Builder $query) use ($account): void {
                $query->where('switch_account_id', $account->getKey());
            });

        $counts = (clone $scope)
            ->select('status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (PaymentWebhookDelivery $delivery): array => [
                $delivery->status->value => (int) $delivery->getAttribute('aggregate'),
            ]);

        $summary = collect(PaymentWebhookDeliveryStatus::cases())
            ->mapWithKeys(fn (PaymentWebhookDeliveryStatus $status): array => [
                $status->value => (int) $counts->get($status->value, 0),
            ])
            ->all();
        $summary['total'] = array_sum($summary);
        $summary['requiring_attention'] = $summary[PaymentWebhookDeliveryStatus::RetryPending->value]
            + $summary[PaymentWebhookDeliveryStatus::Failed->value];

        $deliveries = (clone $scope)
            ->with('paymentAttempt')
            ->orderByDesc('received_at')
            ->orderByDesc('payment_webhook_delivery_id')
            ->limit(max(1, min(50, $limit)))
            ->get();

        return [
            'summary' => $summary,
            'recovery_available' => $this->recovery->available(),
            'deliveries' => $deliveries,
        ];
    }
}
