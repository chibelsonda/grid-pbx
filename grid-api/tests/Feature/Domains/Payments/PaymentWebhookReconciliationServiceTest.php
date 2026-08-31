<?php

namespace Tests\Feature\Domains\Payments;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Contracts\PaymentTransactionStatusGateway;
use App\Domains\Payments\Dto\PaymentTransactionStatusResult;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Enums\PaymentWebhookDeliveryStatus;
use App\Domains\Payments\Exceptions\PaymentWebhookRetryException;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Models\PaymentWebhookDelivery;
use App\Domains\Payments\Services\PaymentWebhookReconciliationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentWebhookReconciliationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_resolves_an_indeterminate_attempt_and_records_only_safe_state(): void
    {
        $account = SwitchAccount::factory()->create();
        $attempt = $this->attempt($account, PaymentAttemptStatus::Indeterminate);
        $delivery = $this->delivery(substr($attempt->id, 0, 20));
        $this->mock(PaymentTransactionStatusGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('status')
                ->once()
                ->with('private-provider-transaction-id', PaymentOperation::Charge)
                ->andReturn(new PaymentTransactionStatusResult(
                    PaymentAttemptStatus::Succeeded,
                    'settled',
                ));
        });

        app(PaymentWebhookReconciliationService::class)->reconcile($delivery->id);

        $attempt->refresh();
        $delivery->refresh();
        $storedAttempt = DB::table('payment_attempts')
            ->where('payment_attempt_id', $attempt->getKey())
            ->first();
        $storedDelivery = DB::table('payment_webhook_deliveries')
            ->where('payment_webhook_delivery_id', $delivery->getKey())
            ->first();

        $this->assertSame(PaymentAttemptStatus::Succeeded, $attempt->status);
        $this->assertSame('settled', $attempt->provider_status);
        $this->assertNotNull($attempt->reconciled_at);
        $this->assertSame('private-provider-transaction-id', $attempt->provider_reference);
        $this->assertNotSame('private-provider-transaction-id', $storedAttempt->provider_reference);
        $this->assertNotSame('private-provider-transaction-id', $storedDelivery->provider_reference);
        $this->assertSame(PaymentWebhookDeliveryStatus::Processed, $delivery->status);
        $this->assertSame($attempt->getKey(), $delivery->payment_attempt_id);
        $this->assertDatabaseHas('payment_attempt_events', [
            'payment_attempt_id' => $attempt->getKey(),
            'event_type' => 'webhook_reconciled',
            'status' => 'succeeded',
        ]);
    }

    public function test_it_safely_ignores_a_transaction_that_does_not_belong_to_gridpbx(): void
    {
        $delivery = $this->delivery('external-reference');
        $this->mock(PaymentTransactionStatusGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('status');
        });

        app(PaymentWebhookReconciliationService::class)->reconcile($delivery->id);

        $delivery->refresh();
        $this->assertSame(PaymentWebhookDeliveryStatus::Ignored, $delivery->status);
        $this->assertSame('unmatched_transaction', $delivery->safe_error_code);
    }

    public function test_it_marks_transient_provider_failures_for_a_bounded_job_retry(): void
    {
        $account = SwitchAccount::factory()->create();
        $attempt = $this->attempt($account, PaymentAttemptStatus::Indeterminate);
        $delivery = $this->delivery(substr($attempt->id, 0, 20));
        $this->mock(PaymentTransactionStatusGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('status')->once()->andReturn(new PaymentTransactionStatusResult(
                PaymentAttemptStatus::Indeterminate,
                'unavailable',
                'provider_connection_interrupted',
                true,
            ));
        });

        try {
            app(PaymentWebhookReconciliationService::class)->reconcile($delivery->id);
            $this->fail('A transient provider failure should request a queue retry.');
        } catch (PaymentWebhookRetryException) {
            $delivery->refresh();
            $this->assertSame(PaymentWebhookDeliveryStatus::RetryPending, $delivery->status);
            $this->assertSame('provider_connection_interrupted', $delivery->safe_error_code);
            $this->assertSame(1, $delivery->processing_attempts);
        }
    }

    private function attempt(
        SwitchAccount $account,
        PaymentAttemptStatus $status,
    ): PaymentAttempt {
        return PaymentAttempt::query()->create([
            'switch_account_id' => $account->getKey(),
            'provider' => 'authorize_net',
            'operation' => PaymentOperation::Charge,
            'idempotency_hash' => hash('sha256', fake()->uuid()),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
            'amount' => '1.00000000',
            'currency' => 'USD',
            'status' => $status,
            'safe_error_code' => 'provider_processing_interrupted',
        ]);
    }

    private function delivery(string $merchantReference): PaymentWebhookDelivery
    {
        $providerReference = 'private-provider-transaction-id';

        return PaymentWebhookDelivery::query()->create([
            'provider' => 'authorize_net',
            'notification_hash' => hash('sha256', fake()->uuid()),
            'event_type' => 'net.authorize.payment.authcapture.created',
            'entity_name' => 'transaction',
            'provider_reference' => $providerReference,
            'provider_reference_hash' => $this->secureHash($providerReference),
            'merchant_reference' => $merchantReference,
            'status' => PaymentWebhookDeliveryStatus::Received,
            'received_at' => now(),
        ]);
    }

    private function secureHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
