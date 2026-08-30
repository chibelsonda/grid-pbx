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
            ->assertJsonStructure(['data' => ['observed_at']])
            ->assertJsonMissingPath('data.switch_account_id')
            ->assertDontSee($account->switch_account_id)
            ->assertDontSee('Call-ID')
            ->assertDontSee('Presence-ID');
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
