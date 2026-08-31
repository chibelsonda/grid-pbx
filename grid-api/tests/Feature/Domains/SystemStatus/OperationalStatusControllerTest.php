<?php

namespace Tests\Feature\Domains\SystemStatus;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SystemStatus\Contracts\SwitchOperationalStatusGateway;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperationalStatusControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_accessible_user_views_only_the_safe_operational_summary(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchOperationalStatusGateway::class)
            ->shouldReceive('inspect')
            ->once()
            ->withArgs(fn (SwitchAccount $received): bool => $received->is($account))
            ->andReturn([
                'presence_subscription_diagnostics_available' => true,
                'parked_call_summary_available' => true,
                'active_parked_call_count' => 2,
                'webhook_event_catalog_available' => true,
                'webhook_available_event_count' => 9,
                'webhook_configuration_summary_available' => true,
                'webhook_configured_count' => 3,
                'webhook_enabled_count' => 2,
                'sms_inventory_available' => false,
                'mms_inventory_available' => false,
                'port_request_inventory_available' => true,
                'number_carrier_configuration_available' => true,
            ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/operational-status");

        $response->assertOk()
            ->assertJsonPath('data.presence.subscription_diagnostics_available', true)
            ->assertJsonPath('data.presence.live_status_available', false)
            ->assertJsonPath('data.presence.commands_available', false)
            ->assertJsonPath('data.parking.summary_available', true)
            ->assertJsonPath('data.parking.active_call_count', 2)
            ->assertJsonPath('data.parking.actions_available', false)
            ->assertJsonPath('data.webhooks.event_catalog_available', true)
            ->assertJsonPath('data.webhooks.available_event_count', 9)
            ->assertJsonPath('data.webhooks.configuration_summary_available', true)
            ->assertJsonPath('data.webhooks.configured_count', 3)
            ->assertJsonPath('data.webhooks.enabled_count', 2)
            ->assertJsonPath('data.webhooks.configuration_mutations_available', false)
            ->assertJsonPath('data.webhooks.delivery_history_available', false)
            ->assertJsonPath('data.messaging.sms_inventory_available', false)
            ->assertJsonPath('data.messaging.mms_inventory_available', false)
            ->assertJsonPath('data.messaging.message_content_available', false)
            ->assertJsonPath('data.messaging.sending_available', false)
            ->assertJsonPath('data.number_porting.inventory_available', true)
            ->assertJsonPath('data.number_porting.request_details_available', false)
            ->assertJsonPath('data.number_porting.documents_available', false)
            ->assertJsonPath('data.number_porting.workflow_mutations_available', false)
            ->assertJsonPath('data.number_management.carrier_configuration_available', true)
            ->assertJsonPath('data.number_management.search_available', false)
            ->assertJsonPath('data.number_management.purchase_available', false)
            ->assertJsonPath('data.number_management.reservation_available', false)
            ->assertJsonPath('data.number_management.release_available', false)
            ->assertJsonStructure(['data' => ['observed_at']])
            ->assertJsonMissingPath('data.switch_account_id')
            ->assertDontSee($account->switch_account_id)
            ->assertDontSee('Call-ID')
            ->assertDontSee('Presence-ID')
            ->assertDontSee('hook_id')
            ->assertDontSee('uri')
            ->assertDontSee('req_body')
            ->assertDontSee('message_id')
            ->assertDontSee('private SMS body')
            ->assertDontSee('+15550000001')
            ->assertDontSee('private-billing-account')
            ->assertDontSee('private-port-pin')
            ->assertDontSee('raw-port-request-id')
            ->assertDontSee('usable_carriers')
            ->assertDontSee('carrier_modules')
            ->assertDontSee('accept_charges')
            ->assertDontSee('quotes');
    }

    public function test_returns_401_for_an_unauthenticated_request(): void
    {
        $account = SwitchAccount::factory()->create();
        $this->mock(SwitchOperationalStatusGateway::class)->shouldNotReceive('inspect');

        $this->getJson("/api/v1/accounts/{$account->id}/operational-status")
            ->assertUnauthorized();
    }

    public function test_returns_404_for_another_organizations_status(): void
    {
        [, $account] = $this->accessibleAccount();
        $outsider = User::factory()->create();
        $this->mock(SwitchOperationalStatusGateway::class)->shouldNotReceive('inspect');

        $this->actingAs($outsider)
            ->getJson("/api/v1/accounts/{$account->id}/operational-status")
            ->assertNotFound();
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, [
            'role' => OrganizationRole::ReadOnlyUser->value,
        ]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
