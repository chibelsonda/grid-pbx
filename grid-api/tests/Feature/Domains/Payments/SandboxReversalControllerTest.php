<?php

namespace Tests\Feature\Domains\Payments;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Contracts\PaymentReversalGateway;
use App\Domains\Payments\Dto\PaymentMutationResult;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SandboxReversalControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_void_is_idempotent_and_keeps_provider_references_private(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $source = $this->successfulCharge($account);
        $this->configureSandbox();
        $this->mock(PaymentReversalGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('void')
                ->once()
                ->with('private-source-transaction', Mockery::type('string'))
                ->andReturn(new PaymentMutationResult(
                    PaymentAttemptStatus::Succeeded,
                    'private-void-transaction',
                ));
        });

        $request = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'sandbox-void-key-000001');
        $endpoint = $this->voidEndpoint($account, $source);

        $first = $request->postJson($endpoint, ['confirmation' => true]);
        $first->assertCreated()
            ->assertJsonPath('data.operation', 'void')
            ->assertJsonPath('data.amount', '1.00000000')
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('meta.replayed', false)
            ->assertJsonMissing(['private-source-transaction'])
            ->assertJsonMissing(['private-void-transaction']);

        $request->postJson($endpoint, ['confirmation' => true])
            ->assertOk()
            ->assertHeader('Idempotent-Replay', 'true')
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('payment_attempts', 2);
        $this->assertDatabaseCount('payment_attempt_events', 2);
    }

    public function test_refund_prevents_the_successful_total_from_exceeding_the_charge(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $source = $this->successfulCharge($account);
        $this->configureSandbox();
        $this->mock(PaymentReversalGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refund')
                ->once()
                ->andReturn(new PaymentMutationResult(
                    PaymentAttemptStatus::Succeeded,
                    'private-refund-transaction',
                ));
        });

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'sandbox-refund-key-0001')
            ->postJson($this->refundEndpoint($account, $source), [
                'amount_minor' => 60,
                'currency' => 'USD',
                'confirmation' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'succeeded');

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'sandbox-refund-key-0002')
            ->postJson($this->refundEndpoint($account, $source), [
                'amount_minor' => 50,
                'currency' => 'USD',
                'confirmation' => true,
            ])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'The refund exceeds the remaining charge balance.',
            ]);

        $this->assertDatabaseCount('payment_attempts', 2);
    }

    public function test_reversals_fail_closed_and_do_not_accept_provider_references_from_the_ui(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $source = $this->successfulCharge($account);
        $this->configureSandbox(false);
        $this->mock(PaymentReversalGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('void');
            $mock->shouldNotReceive('refund');
        });

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'sandbox-void-disabled-001')
            ->postJson($this->voidEndpoint($account, $source), ['confirmation' => true])
            ->assertConflict();

        $this->configureSandbox();
        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'sandbox-refund-prohibited-01')
            ->postJson($this->refundEndpoint($account, $source), [
                'amount_minor' => 100,
                'currency' => 'USD',
                'confirmation' => true,
                'provider_reference' => 'ui-supplied-provider-reference',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider_reference');

        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_pending_refund_reserves_the_charge_balance_until_reconciliation(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $source = $this->successfulCharge($account);
        $this->configureSandbox();
        PaymentAttempt::query()->create([
            'switch_account_id' => $account->getKey(),
            'source_payment_attempt_id' => $source->getKey(),
            'provider' => 'authorize_net',
            'operation' => PaymentOperation::Refund,
            'idempotency_hash' => hash('sha256', 'pending-refund-idempotency'),
            'request_fingerprint' => hash('sha256', 'pending-refund-fingerprint'),
            'amount' => '0.75',
            'currency' => 'USD',
            'status' => PaymentAttemptStatus::Pending,
        ]);
        $this->mock(PaymentReversalGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('refund');
        });

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'sandbox-reserved-refund-01')
            ->postJson($this->refundEndpoint($account, $source), [
                'amount_minor' => 50,
                'currency' => 'USD',
                'confirmation' => true,
            ])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'The refund exceeds the remaining charge balance.',
            ]);

        $this->assertDatabaseCount('payment_attempts', 2);
    }

    public function test_reversals_require_account_administration_and_hide_other_tenants(): void
    {
        [$operator, $account] = $this->accessibleAccount(OrganizationRole::AccountOperator);
        $source = $this->successfulCharge($account);
        $this->configureSandbox();
        $this->mock(PaymentReversalGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('void');
            $mock->shouldNotReceive('refund');
        });

        $operatorRequest = $this->actingAs($operator)
            ->withHeader('Idempotency-Key', 'operator-reversal-key-0001');
        $operatorRequest
            ->postJson($this->voidEndpoint($account, $source), ['confirmation' => true])
            ->assertForbidden();
        $operatorRequest
            ->postJson($this->refundEndpoint($account, $source), [
                'amount_minor' => 100,
                'currency' => 'USD',
                'confirmation' => true,
            ])
            ->assertForbidden();

        [$administrator] = $this->accessibleAccount();
        $administratorRequest = $this->actingAs($administrator)
            ->withHeader('Idempotency-Key', 'cross-tenant-reversal-0001');
        $administratorRequest
            ->postJson($this->voidEndpoint($account, $source), ['confirmation' => true])
            ->assertNotFound();
        $administratorRequest
            ->postJson($this->refundEndpoint($account, $source), [
                'amount_minor' => 100,
                'currency' => 'USD',
                'confirmation' => true,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('payment_attempts', 1);
    }

    private function configureSandbox(bool $enabled = true): void
    {
        config()->set([
            'payments.enabled' => true,
            'payments.mutations_enabled' => true,
            'payments.provider' => 'authorize_net',
            'payments.authorize_net.environment' => 'sandbox',
            'payments.authorize_net.api_login_id' => 'sandbox-login',
            'payments.authorize_net.transaction_key' => 'sandbox-transaction-key',
            'payments.authorize_net.sandbox_void_enabled' => $enabled,
            'payments.authorize_net.sandbox_refund_enabled' => $enabled,
            'payments.authorize_net.sandbox_max_refund_minor' => 100,
        ]);
    }

    private function successfulCharge(SwitchAccount $account): PaymentAttempt
    {
        return PaymentAttempt::query()->create([
            'switch_account_id' => $account->getKey(),
            'provider' => 'authorize_net',
            'operation' => PaymentOperation::Charge,
            'idempotency_hash' => hash('sha256', 'source-idempotency'),
            'request_fingerprint' => hash('sha256', 'source-fingerprint'),
            'amount' => '1.00',
            'currency' => 'USD',
            'status' => PaymentAttemptStatus::Succeeded,
            'provider_reference' => 'private-source-transaction',
            'provider_reference_hash' => hash('sha256', 'private-source-transaction'),
            'completed_at' => now(),
        ]);
    }

    private function voidEndpoint(SwitchAccount $account, PaymentAttempt $source): string
    {
        return "/api/v1/accounts/{$account->id}/payments/attempts/{$source->id}/sandbox-void";
    }

    private function refundEndpoint(SwitchAccount $account, PaymentAttempt $source): string
    {
        return "/api/v1/accounts/{$account->id}/payments/attempts/{$source->id}/sandbox-refunds";
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(
        OrganizationRole $role = OrganizationRole::AccountAdministrator,
    ): array {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
