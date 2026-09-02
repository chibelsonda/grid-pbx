<?php

namespace Tests\Feature\Domains\Payments;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Enums\PaymentWebhookDeliveryStatus;
use App\Domains\Payments\Jobs\ReconcilePaymentWebhookJob;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Models\PaymentWebhookDelivery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentWebhookDeliveryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_views_only_safe_account_scoped_webhook_health(): void
    {
        $this->configureUnavailableRecovery();
        [$user, $account, $organization] = $this->accessibleAccount();
        $otherAccount = SwitchAccount::factory()->for($organization)->create();
        $processed = $this->delivery($this->attempt($account), PaymentWebhookDeliveryStatus::Processed);
        $failed = $this->delivery($this->attempt($account), PaymentWebhookDeliveryStatus::Failed);
        $this->delivery($this->attempt($otherAccount), PaymentWebhookDeliveryStatus::Failed);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/payments/webhook-deliveries");

        $response->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.processed', 1)
            ->assertJsonPath('data.summary.failed', 1)
            ->assertJsonPath('data.summary.requiring_attention', 1)
            ->assertJsonPath('data.recovery_available', false)
            ->assertJsonCount(2, 'data.deliveries')
            ->assertJsonFragment(['id' => $processed->id, 'can_retry' => false])
            ->assertJsonFragment(['id' => $failed->id, 'can_retry' => true])
            ->assertJsonMissing(['private-provider-reference'])
            ->assertJsonMissing(['private-notification-hash']);

        $this->assertStringNotContainsString('private-provider-reference', $response->getContent());
        $this->assertStringNotContainsString('provider_reference', $response->getContent());
        $this->assertStringNotContainsString('notification_hash', $response->getContent());
    }

    public function test_webhook_health_rejects_an_invalid_limit(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/payments/webhook-deliveries?limit=none")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
    }

    public function test_account_operator_cannot_view_webhook_health(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountOperator);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/payments/webhook-deliveries")
            ->assertForbidden();
    }

    public function test_administrator_queues_one_bounded_retry_for_a_failed_delivery(): void
    {
        Queue::fake([ReconcilePaymentWebhookJob::class]);
        $this->configureRecovery();
        [$user, $account] = $this->accessibleAccount();
        $delivery = $this->delivery($this->attempt($account), PaymentWebhookDeliveryStatus::Failed);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/payments/webhook-deliveries/{$delivery->id}/retry")
            ->assertAccepted()
            ->assertJsonPath('data.id', $delivery->id)
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.safe_error_code', null);

        $this->assertDatabaseHas('payment_webhook_deliveries', [
            'payment_webhook_delivery_id' => $delivery->getKey(),
            'status' => 'received',
            'safe_error_code' => null,
            'processed_at' => null,
        ]);
        $this->assertDatabaseHas('payment_attempt_events', [
            'payment_attempt_id' => $delivery->payment_attempt_id,
            'event_type' => 'webhook_retry_requested',
            'status' => 'indeterminate',
        ]);
        $event = PaymentAttempt::query()
            ->findOrFail($delivery->payment_attempt_id)
            ->events()
            ->where('event_type', 'webhook_retry_requested')
            ->firstOrFail();
        $this->assertSame($delivery->id, $event->safe_context['delivery_id']);
        $this->assertSame($user->id, $event->safe_context['requested_by']);
        $this->assertNotSame('127.0.0.1', $event->safe_context['request_ip_hash']);
        Queue::assertPushed(
            ReconcilePaymentWebhookJob::class,
            fn (ReconcilePaymentWebhookJob $job): bool => $job->deliveryId === $delivery->id,
        );
    }

    public function test_account_operator_cannot_retry_a_failed_delivery(): void
    {
        Queue::fake([ReconcilePaymentWebhookJob::class]);
        $this->configureRecovery();
        [$operator, $account] = $this->accessibleAccount(OrganizationRole::AccountOperator);
        $delivery = $this->delivery($this->attempt($account), PaymentWebhookDeliveryStatus::Failed);

        $this->actingAs($operator)
            ->postJson("/api/v1/accounts/{$account->id}/payments/webhook-deliveries/{$delivery->id}/retry")
            ->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('payment_webhook_deliveries', [
            'payment_webhook_delivery_id' => $delivery->getKey(),
            'status' => 'failed',
        ]);
    }

    public function test_retry_does_not_disclose_or_recover_another_accounts_delivery(): void
    {
        Queue::fake([ReconcilePaymentWebhookJob::class]);
        $this->configureRecovery();
        [$user, $account, $organization] = $this->accessibleAccount();
        $otherAccount = SwitchAccount::factory()->for($organization)->create();
        $delivery = $this->delivery(
            $this->attempt($otherAccount),
            PaymentWebhookDeliveryStatus::Failed,
        );

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/payments/webhook-deliveries/{$delivery->id}/retry")
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'The webhook delivery is unavailable for this account.',
            ]);

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('payment_webhook_deliveries', [
            'payment_webhook_delivery_id' => $delivery->getKey(),
            'status' => 'failed',
        ]);
    }

    public function test_final_delivery_and_unavailable_provider_recovery_are_rejected_safely(): void
    {
        Queue::fake([ReconcilePaymentWebhookJob::class]);
        $this->configureUnavailableRecovery();
        [$user, $account] = $this->accessibleAccount();
        $delivery = $this->delivery($this->attempt($account), PaymentWebhookDeliveryStatus::Failed);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/payments/webhook-deliveries/{$delivery->id}/retry")
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Sandbox provider status verification is unavailable. Verify provider configuration before retrying.',
            ]);

        $this->configureRecovery();
        $delivery->forceFill(['status' => PaymentWebhookDeliveryStatus::Processed])->save();

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/payments/webhook-deliveries/{$delivery->id}/retry")
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Only failed webhook reconciliation can be retried.',
            ]);

        Queue::assertNothingPushed();
    }

    /** @return array{User, SwitchAccount, Organization} */
    private function accessibleAccount(
        OrganizationRole $role = OrganizationRole::AccountAdministrator,
    ): array {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create(), $organization];
    }

    private function attempt(SwitchAccount $account): PaymentAttempt
    {
        return PaymentAttempt::query()->create([
            'switch_account_id' => $account->getKey(),
            'provider' => 'authorize_net',
            'operation' => PaymentOperation::Charge,
            'idempotency_hash' => hash('sha256', fake()->uuid()),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
            'amount' => '1.00000000',
            'currency' => 'USD',
            'status' => PaymentAttemptStatus::Indeterminate,
        ]);
    }

    private function delivery(
        PaymentAttempt $attempt,
        PaymentWebhookDeliveryStatus $status,
    ): PaymentWebhookDelivery {
        return PaymentWebhookDelivery::query()->create([
            'provider' => 'authorize_net',
            'notification_hash' => hash('sha256', 'private-notification-hash'.fake()->uuid()),
            'event_type' => 'net.authorize.payment.authcapture.created',
            'entity_name' => 'transaction',
            'provider_reference' => 'private-provider-reference',
            'provider_reference_hash' => hash('sha256', fake()->uuid()),
            'payment_attempt_id' => $attempt->getKey(),
            'status' => $status,
            'processing_attempts' => $status === PaymentWebhookDeliveryStatus::Failed ? 5 : 1,
            'safe_error_code' => $status === PaymentWebhookDeliveryStatus::Failed
                ? 'reconciliation_exhausted'
                : null,
            'received_at' => now(),
            'processed_at' => $status === PaymentWebhookDeliveryStatus::Received ? null : now(),
        ]);
    }

    private function configureRecovery(): void
    {
        config()->set([
            'payments.provider' => 'authorize_net',
            'payments.authorize_net.environment' => 'sandbox',
            'payments.authorize_net.api_login_id' => 'sandbox-login',
            'payments.authorize_net.transaction_key' => 'private-transaction-key',
        ]);
    }

    private function configureUnavailableRecovery(): void
    {
        config()->set([
            'payments.provider' => 'authorize_net',
            'payments.authorize_net.environment' => 'production',
            'payments.authorize_net.api_login_id' => 'configured-login',
            'payments.authorize_net.transaction_key' => 'configured-transaction-key',
        ]);
    }
}
