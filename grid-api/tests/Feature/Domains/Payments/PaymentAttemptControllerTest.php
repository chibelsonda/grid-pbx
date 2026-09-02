<?php

namespace Tests\Feature\Domains\Payments;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PaymentAttemptControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_lists_only_safe_attempts_for_the_selected_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        [, $otherAccount] = $this->accessibleAccount();
        $charge = $this->attempt($account, PaymentOperation::Charge, 'private-charge-reference');
        $refund = $this->attempt(
            $account,
            PaymentOperation::Refund,
            'private-refund-reference',
            $charge,
        );
        $this->attempt($otherAccount, PaymentOperation::Charge, 'other-tenant-reference');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/payments/attempts");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $refund->id)
            ->assertJsonPath('data.0.source_attempt_id', $charge->id)
            ->assertJsonPath('data.0.operation', 'refund')
            ->assertJsonPath('data.1.id', $charge->id)
            ->assertJsonMissing(['private-charge-reference'])
            ->assertJsonMissing(['private-refund-reference'])
            ->assertJsonMissing(['other-tenant-reference']);
    }

    public function test_attempt_history_rejects_an_invalid_limit(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/payments/attempts?limit=51")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
    }

    public function test_attempt_history_is_tenant_scoped_and_requires_account_administration(): void
    {
        [$operator, $account] = $this->accessibleAccount(OrganizationRole::AccountOperator);

        $this->actingAs($operator)
            ->getJson("/api/v1/accounts/{$account->id}/payments/attempts")
            ->assertForbidden();

        [$administrator] = $this->accessibleAccount();
        $this->actingAs($administrator)
            ->getJson("/api/v1/accounts/{$account->id}/payments/attempts")
            ->assertNotFound();
    }

    public function test_administrator_views_a_sanitized_attempt_event_timeline(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $attempt = $this->attempt($account, PaymentOperation::Charge, 'private-charge-reference');
        $event = $attempt->events()->create([
            'event_type' => 'webhook_reconciled',
            'status' => PaymentAttemptStatus::Succeeded,
            'provider_reference_hash' => hash('sha256', 'private-charge-reference'),
            'safe_context' => [
                'safe_error_code' => 'provider_state_not_final',
                'provider_status' => 'settled',
                'delivery_id' => '00000000-0000-4000-8000-000000000099',
                'requested_by' => $user->id,
                'request_ip_hash' => hash('sha256', '127.0.0.1'),
                'provider_reference' => 'private-charge-reference',
                'signature' => 'private-signature',
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/payments/attempts/{$attempt->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $attempt->id)
            ->assertJsonPath('data.events.0.id', $event->id)
            ->assertJsonPath('data.events.0.event_type', 'webhook_reconciled')
            ->assertJsonPath('data.events.0.status', 'succeeded')
            ->assertJsonPath('data.events.0.safe_error_code', 'provider_state_not_final')
            ->assertJsonPath('data.events.0.provider_status', 'settled')
            ->assertJsonMissing(['private-charge-reference'])
            ->assertJsonMissing(['private-signature'])
            ->assertJsonMissing(['request_ip_hash'])
            ->assertJsonMissing(['requested_by'])
            ->assertJsonMissing(['delivery_id'])
            ->assertJsonMissing(['provider_reference_hash'])
            ->assertJsonMissing(['safe_context']);
    }

    public function test_attempt_detail_returns_404_for_another_account_and_403_for_an_operator(): void
    {
        [$administrator, $account] = $this->accessibleAccount();
        [, $otherAccount] = $this->accessibleAccount();
        $otherAttempt = $this->attempt(
            $otherAccount,
            PaymentOperation::Charge,
            'other-tenant-reference',
        );

        $this->actingAs($administrator)
            ->getJson("/api/v1/accounts/{$account->id}/payments/attempts/{$otherAttempt->id}")
            ->assertNotFound();

        [$operator, $operatorAccount] = $this->accessibleAccount(OrganizationRole::AccountOperator);
        $operatorAttempt = $this->attempt(
            $operatorAccount,
            PaymentOperation::Charge,
            'operator-account-reference',
        );

        $this->actingAs($operator)
            ->getJson("/api/v1/accounts/{$operatorAccount->id}/payments/attempts/{$operatorAttempt->id}")
            ->assertForbidden();
    }

    private function attempt(
        SwitchAccount $account,
        PaymentOperation $operation,
        string $providerReference,
        ?PaymentAttempt $source = null,
    ): PaymentAttempt {
        return PaymentAttempt::query()->create([
            'switch_account_id' => $account->getKey(),
            'source_payment_attempt_id' => $source?->getKey(),
            'provider' => 'authorize_net',
            'operation' => $operation,
            'idempotency_hash' => hash('sha256', $account->id.$operation->value.$providerReference),
            'request_fingerprint' => hash('sha256', $providerReference),
            'amount' => $operation === PaymentOperation::AttachPaymentMethod ? null : '1.00000000',
            'currency' => $operation === PaymentOperation::AttachPaymentMethod ? null : 'USD',
            'status' => PaymentAttemptStatus::Succeeded,
            'provider_reference' => $providerReference,
            'provider_reference_hash' => hash('sha256', $providerReference),
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
