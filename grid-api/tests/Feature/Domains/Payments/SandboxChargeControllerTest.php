<?php

namespace Tests\Feature\Domains\Payments;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Contracts\PaymentChargeGateway;
use App\Domains\Payments\Dto\PaymentMutationResult;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SandboxChargeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_charge_is_fail_closed_until_all_sandbox_flags_are_enabled(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->configureSandbox(false);
        $this->mock(PaymentChargeGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('charge');
        });

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'sandbox-disabled-key-0001')
            ->postJson($this->endpoint($account), $this->payload())
            ->assertConflict()
            ->assertExactJson(['message' => 'Sandbox payment processing is not available.']);

        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_it_rejects_raw_card_data_and_requires_an_idempotency_key(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->configureSandbox();

        $this->actingAs($user)
            ->postJson($this->endpoint($account), [
                ...$this->payload(),
                'card_number' => '4111111111111111',
                'cvv' => '123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key', 'card_number', 'cvv']);

        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_charge_fails_closed_when_the_hosted_tokenization_key_is_missing(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->configureSandbox();
        config()->set('payments.authorize_net.public_client_key');
        $this->mock(PaymentChargeGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('charge');
        });

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'missing-public-client-key-01')
            ->postJson($this->endpoint($account), $this->payload())
            ->assertConflict()
            ->assertExactJson(['message' => 'Sandbox payment processing is not available.']);

        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_it_authorizes_account_management_and_hides_other_tenants(): void
    {
        [$operator, $account] = $this->accessibleAccount(OrganizationRole::AccountOperator);
        $this->configureSandbox();

        $this->actingAs($operator)
            ->withHeader('Idempotency-Key', 'operator-payment-key-0001')
            ->postJson($this->endpoint($account), $this->payload())
            ->assertForbidden();

        [$administrator] = $this->accessibleAccount();
        $this->actingAs($administrator)
            ->withHeader('Idempotency-Key', 'cross-tenant-key-000001')
            ->postJson($this->endpoint($account), $this->payload())
            ->assertNotFound();
    }

    public function test_it_records_a_safe_attempt_and_replays_the_same_request_without_recharging(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->configureSandbox();
        $this->mock(PaymentChargeGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn(new PaymentMutationResult(
                    PaymentAttemptStatus::Succeeded,
                    'private-provider-transaction-id',
                ));
        });

        $request = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'same-sandbox-payment-key-0001');

        $first = $request->postJson($this->endpoint($account), $this->payload());
        $first->assertCreated()
            ->assertJsonPath('data.provider', 'authorize_net')
            ->assertJsonPath('data.operation', 'charge')
            ->assertJsonPath('data.amount', '1.00000000')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('meta.replayed', false)
            ->assertJsonMissing(['private-provider-transaction-id'])
            ->assertJsonMissing(['opaque-payment-token-value']);

        $request->postJson($this->endpoint($account), $this->payload())
            ->assertOk()
            ->assertHeader('Idempotent-Replay', 'true')
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('meta.replayed', true);

        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('payment_attempt_events', 2);
        $this->assertDatabaseMissing('payment_attempts', [
            'provider_reference' => 'private-provider-transaction-id',
        ]);
    }

    public function test_it_rejects_reusing_an_idempotency_key_for_a_different_request(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->configureSandbox();
        $this->mock(PaymentChargeGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn(new PaymentMutationResult(PaymentAttemptStatus::Succeeded, 'transaction-1'));
        });

        $request = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'conflicting-sandbox-key-001');

        $request->postJson($this->endpoint($account), $this->payload())->assertCreated();
        $request->postJson($this->endpoint($account), $this->payload('different-opaque-payment-token'))
            ->assertConflict()
            ->assertExactJson([
                'message' => 'The idempotency key was already used for a different payment request.',
            ]);

        $this->assertDatabaseCount('payment_attempts', 1);
    }

    /** @return array<string, mixed> */
    private function payload(string $token = 'opaque-payment-token-value'): array
    {
        return [
            'amount_minor' => 100,
            'currency' => 'USD',
            'confirmation' => true,
            'opaque_data' => [
                'dataDescriptor' => 'COMMON.ACCEPT.INAPP.PAYMENT',
                'dataValue' => $token,
            ],
        ];
    }

    private function configureSandbox(bool $chargeEnabled = true): void
    {
        config()->set([
            'payments.enabled' => true,
            'payments.mutations_enabled' => true,
            'payments.provider' => 'authorize_net',
            'payments.authorize_net.environment' => 'sandbox',
            'payments.authorize_net.api_login_id' => 'sandbox-login',
            'payments.authorize_net.transaction_key' => 'sandbox-transaction-key',
            'payments.authorize_net.public_client_key' => 'sandbox-public-key',
            'payments.authorize_net.sandbox_charge_enabled' => $chargeEnabled,
            'payments.authorize_net.sandbox_max_charge_minor' => 100,
        ]);
    }

    private function endpoint(SwitchAccount $account): string
    {
        return "/api/v1/accounts/{$account->id}/payments/sandbox-charges";
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
