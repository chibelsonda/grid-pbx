<?php

namespace Tests\Feature\Domains\Payments;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PaymentCapabilityControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_views_fail_closed_payment_capabilities_without_secrets(): void
    {
        [$user, $account] = $this->accessibleAccount();
        config()->set([
            'payments.enabled' => false,
            'payments.mutations_enabled' => true,
            'payments.provider' => 'authorize_net',
            'payments.authorize_net.environment' => 'sandbox',
            'payments.authorize_net.api_login_id' => 'private-login-id',
            'payments.authorize_net.transaction_key' => 'private-transaction-key',
            'payments.authorize_net.public_client_key' => 'private-client-key',
            'payments.authorize_net.signature_key' => 'private-signature-key',
            'payments.authorize_net.sandbox_charge_enabled' => true,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/payments/capabilities");

        $response->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.provider', 'authorize_net')
            ->assertJsonPath('data.environment', 'sandbox')
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.server_accepts_card_data', false)
            ->assertJsonPath('data.diagnostics.available', true)
            ->assertJsonPath('data.diagnostics.sandbox_only', true)
            ->assertJsonPath('data.mutations.charge', false)
            ->assertJsonPath('data.mutations.void', false)
            ->assertJsonPath('data.mutations.refund', false)
            ->assertJsonPath('data.mutations.attach_payment_method', false)
            ->assertJsonPath('data.client.available', false)
            ->assertJsonPath('data.client.api_login_id', null)
            ->assertJsonPath('data.client.public_client_key', null)
            ->assertJsonPath('data.mutations.refund', false)
            ->assertJsonMissing(['private-login-id'])
            ->assertJsonMissing(['private-transaction-key'])
            ->assertJsonMissing(['private-client-key'])
            ->assertJsonMissing(['private-signature-key']);
    }

    public function test_enabled_sandbox_exposes_only_accept_ui_public_configuration(): void
    {
        [$user, $account] = $this->accessibleAccount();
        config()->set([
            'payments.enabled' => true,
            'payments.mutations_enabled' => true,
            'payments.provider' => 'authorize_net',
            'payments.authorize_net.environment' => 'sandbox',
            'payments.authorize_net.api_login_id' => 'public-api-login-id',
            'payments.authorize_net.transaction_key' => 'private-transaction-key',
            'payments.authorize_net.public_client_key' => 'public-client-key',
            'payments.authorize_net.signature_key' => 'private-signature-key',
            'payments.authorize_net.sandbox_charge_enabled' => true,
            'payments.authorize_net.sandbox_void_enabled' => true,
            'payments.authorize_net.sandbox_refund_enabled' => true,
            'payments.authorize_net.sandbox_profile_enabled' => true,
            'payments.authorize_net.sandbox_max_charge_minor' => 100,
            'payments.authorize_net.sandbox_max_refund_minor' => 100,
            'payments.authorize_net.accept_ui_url' => 'https://jstest.authorize.net/v3/AcceptUI.js',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/payments/capabilities");

        $response->assertOk()
            ->assertJsonPath('data.client.available', true)
            ->assertJsonPath('data.client.api_login_id', 'public-api-login-id')
            ->assertJsonPath('data.client.public_client_key', 'public-client-key')
            ->assertJsonPath('data.client.sandbox_max_charge_minor', 100)
            ->assertJsonPath('data.mutations.charge', true)
            ->assertJsonPath('data.mutations.void', true)
            ->assertJsonPath('data.mutations.refund', true)
            ->assertJsonPath('data.mutations.attach_payment_method', true)
            ->assertJsonPath('data.client.sandbox_max_refund_minor', 100)
            ->assertJsonMissing(['private-transaction-key'])
            ->assertJsonMissing(['private-signature-key']);
    }

    public function test_account_operator_cannot_view_payment_capabilities(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountOperator);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/payments/capabilities")
            ->assertForbidden();
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
