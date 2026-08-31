<?php

namespace Tests\Feature\Domains\Payments;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Contracts\PaymentProfileGateway;
use App\Domains\Payments\Dto\PaymentProfileResult;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SandboxPaymentProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_creates_and_replays_a_safe_customer_profile_projection(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $source = $this->successfulCharge($account);
        $this->configureSandbox();
        $this->mock(PaymentProfileGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createFromTransaction')
                ->once()
                ->with(
                    'private-source-transaction',
                    Mockery::type('string'),
                    Mockery::type('string'),
                    Mockery::type('string'),
                )
                ->andReturn(new PaymentProfileResult(
                    PaymentAttemptStatus::Succeeded,
                    'private-customer-profile-id',
                    'private-payment-profile-id',
                ));
        });

        $request = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'sandbox-profile-key-0001');
        $endpoint = $this->endpoint($account, $source);

        $first = $request->postJson($endpoint, ['confirmation' => true]);
        $first->assertCreated()
            ->assertJsonPath('data.attempt.operation', 'attach_payment_method')
            ->assertJsonPath('data.attempt.status', 'succeeded')
            ->assertJsonPath('data.profile.provider', 'authorize_net')
            ->assertJsonPath('data.profile.status', 'active')
            ->assertJsonPath('meta.replayed', false)
            ->assertJsonMissing(['private-source-transaction'])
            ->assertJsonMissing(['private-customer-profile-id'])
            ->assertJsonMissing(['private-payment-profile-id']);

        $request->postJson($endpoint, ['confirmation' => true])
            ->assertOk()
            ->assertHeader('Idempotent-Replay', 'true')
            ->assertJsonPath('data.attempt.id', $first->json('data.attempt.id'))
            ->assertJsonPath('data.profile.id', $first->json('data.profile.id'));

        $this->assertDatabaseCount('payment_attempts', 2);
        $this->assertDatabaseCount('payment_customer_profiles', 1);
        $this->assertDatabaseMissing('payment_customer_profiles', [
            'provider_customer_profile_id' => 'private-customer-profile-id',
        ]);
        $this->assertDatabaseMissing('payment_customer_profiles', [
            'provider_payment_profile_id' => 'private-payment-profile-id',
        ]);
    }

    public function test_profile_creation_is_disabled_by_default(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $source = $this->successfulCharge($account);
        $this->configureSandbox(false);
        $this->mock(PaymentProfileGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createFromTransaction');
        });

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'sandbox-profile-disabled-01')
            ->postJson($this->endpoint($account, $source), ['confirmation' => true])
            ->assertConflict()
            ->assertExactJson(['message' => 'Sandbox payment processing is not available.']);

        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('payment_customer_profiles', 0);
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
            'payments.authorize_net.sandbox_profile_enabled' => $enabled,
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

    private function endpoint(SwitchAccount $account, PaymentAttempt $source): string
    {
        return "/api/v1/accounts/{$account->id}/payments/attempts/{$source->id}/sandbox-customer-profile";
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
