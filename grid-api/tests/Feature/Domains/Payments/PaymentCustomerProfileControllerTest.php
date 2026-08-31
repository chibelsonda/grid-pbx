<?php

namespace Tests\Feature\Domains\Payments;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Models\PaymentCustomerProfile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PaymentCustomerProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_lists_only_safe_profiles_for_the_selected_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        [, $otherAccount] = $this->accessibleAccount();
        $older = $this->profile($account, 'private-customer-1', 'private-payment-1', 'XXXX1111');
        $newer = $this->profile($account, 'private-customer-2', 'private-payment-2', 'XXXX2222');
        $this->profile($otherAccount, 'other-customer', 'other-payment', 'XXXX9999');
        $older->forceFill(['created_at' => now()->subMinute()])->save();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/payments/customer-profiles");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.masked_account', 'XXXX2222')
            ->assertJsonPath('data.0.account_type', 'Visa')
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonMissing(['private-customer-1'])
            ->assertJsonMissing(['private-payment-1'])
            ->assertJsonMissing(['private-customer-2'])
            ->assertJsonMissing(['private-payment-2'])
            ->assertJsonMissing(['other-customer'])
            ->assertJsonMissing(['other-payment'])
            ->assertJsonMissing(['payment_customer_profile_id'])
            ->assertJsonMissing(['provider_customer_profile_hash'])
            ->assertJsonMissing(['provider_payment_profile_hash']);
    }

    public function test_profile_inventory_returns_403_for_operator_and_404_for_inaccessible_account(): void
    {
        [$operator, $account] = $this->accessibleAccount(OrganizationRole::AccountOperator);

        $this->actingAs($operator)
            ->getJson("/api/v1/accounts/{$account->id}/payments/customer-profiles")
            ->assertForbidden();

        [$administrator] = $this->accessibleAccount();
        $this->actingAs($administrator)
            ->getJson("/api/v1/accounts/{$account->id}/payments/customer-profiles")
            ->assertNotFound();
    }

    private function profile(
        SwitchAccount $account,
        string $customerReference,
        string $paymentReference,
        string $maskedAccount,
    ): PaymentCustomerProfile {
        $source = $this->attempt($account, PaymentOperation::Charge, $customerReference.'-source');
        $createdBy = $this->attempt(
            $account,
            PaymentOperation::AttachPaymentMethod,
            $customerReference.'-created-by',
            $source,
        );

        return PaymentCustomerProfile::query()->create([
            'switch_account_id' => $account->getKey(),
            'source_payment_attempt_id' => $source->getKey(),
            'created_by_payment_attempt_id' => $createdBy->getKey(),
            'provider' => 'authorize_net',
            'provider_customer_profile_id' => $customerReference,
            'provider_customer_profile_hash' => hash('sha256', $customerReference),
            'provider_payment_profile_id' => $paymentReference,
            'provider_payment_profile_hash' => hash('sha256', $paymentReference),
            'status' => 'active',
            'masked_account' => $maskedAccount,
            'account_type' => 'Visa',
        ]);
    }

    private function attempt(
        SwitchAccount $account,
        PaymentOperation $operation,
        string $reference,
        ?PaymentAttempt $source = null,
    ): PaymentAttempt {
        return PaymentAttempt::query()->create([
            'switch_account_id' => $account->getKey(),
            'source_payment_attempt_id' => $source?->getKey(),
            'provider' => 'authorize_net',
            'operation' => $operation,
            'idempotency_hash' => hash('sha256', $reference.'-idempotency'),
            'request_fingerprint' => hash('sha256', $reference.'-fingerprint'),
            'amount' => $operation === PaymentOperation::Charge ? '1.00' : null,
            'currency' => $operation === PaymentOperation::Charge ? 'USD' : null,
            'status' => PaymentAttemptStatus::Succeeded,
            'provider_reference' => $reference,
            'provider_reference_hash' => hash('sha256', $reference),
            'completed_at' => now(),
        ]);
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
