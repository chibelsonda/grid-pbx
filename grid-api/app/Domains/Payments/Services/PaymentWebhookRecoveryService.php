<?php

namespace App\Domains\Payments\Services;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Enums\PaymentWebhookDeliveryStatus;
use App\Domains\Payments\Exceptions\PaymentWebhookDeliveryNotFoundException;
use App\Domains\Payments\Exceptions\PaymentWebhookRecoveryUnavailableException;
use App\Domains\Payments\Jobs\ReconcilePaymentWebhookJob;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Models\PaymentWebhookDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class PaymentWebhookRecoveryService
{
    public function retry(
        SwitchAccount $account,
        string $publicId,
        User $actor,
        ?string $requestIp,
    ): PaymentWebhookDelivery {
        if (! $this->available()) {
            throw new PaymentWebhookRecoveryUnavailableException('Sandbox provider status verification is unavailable. Verify provider configuration before retrying.');
        }

        $delivery = DB::transaction(function () use (
            $account,
            $publicId,
            $actor,
            $requestIp,
        ): PaymentWebhookDelivery {
            $delivery = PaymentWebhookDelivery::query()
                ->where('id', $publicId)
                ->whereHas('paymentAttempt', function (Builder $query) use ($account): void {
                    $query->where('switch_account_id', $account->getKey());
                })
                ->lockForUpdate()
                ->first();

            if ($delivery === null) {
                throw new PaymentWebhookDeliveryNotFoundException;
            }

            if ($delivery->status !== PaymentWebhookDeliveryStatus::Failed) {
                throw new PaymentWebhookRecoveryUnavailableException('Only failed webhook reconciliation can be retried.');
            }

            if ($delivery->processing_attempts >= 10) {
                throw new PaymentWebhookRecoveryUnavailableException('The webhook recovery limit was reached. Verify provider configuration before retrying.');
            }

            $delivery->forceFill([
                'status' => PaymentWebhookDeliveryStatus::Received,
                'safe_error_code' => null,
                'processed_at' => null,
            ])->save();

            $attempt = PaymentAttempt::query()
                ->lockForUpdate()
                ->findOrFail($delivery->payment_attempt_id);
            $attempt->events()->create([
                'event_type' => 'webhook_retry_requested',
                'status' => $attempt->status,
                'safe_context' => [
                    'delivery_id' => $delivery->id,
                    'requested_by' => $actor->id,
                    'request_ip_hash' => filled($requestIp)
                        ? hash_hmac('sha256', (string) $requestIp, (string) config('app.key'))
                        : null,
                ],
            ]);

            return $delivery;
        });

        ReconcilePaymentWebhookJob::dispatch($delivery->id);

        return $delivery->load('paymentAttempt');
    }

    public function available(): bool
    {
        return config('payments.provider') === 'authorize_net'
            && strtolower((string) config('payments.authorize_net.environment')) === 'sandbox'
            && filled(config('payments.authorize_net.api_login_id'))
            && filled(config('payments.authorize_net.transaction_key'));
    }
}
