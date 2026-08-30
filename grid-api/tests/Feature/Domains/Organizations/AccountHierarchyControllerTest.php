<?php

namespace Tests\Feature\Domains\Organizations;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Models\SwitchServiceSummary;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccountHierarchyControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_refresh_projects_only_managed_hierarchy_and_exposes_public_identifiers(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $parent = SwitchAccount::factory()->for($organization)->create([
            'switch_account_id' => 'switch-parent',
            'name' => 'Parent reseller',
        ]);
        $account = SwitchAccount::factory()->for($organization)->create([
            'switch_account_id' => 'switch-account',
            'name' => 'Managed account',
        ]);
        $child = SwitchAccount::factory()->for($organization)->create([
            'switch_account_id' => 'switch-child',
            'name' => 'Managed child',
        ]);
        $gateway = $this->mock(SwitchAccountGateway::class);
        $gateway->shouldReceive('find')->once()->andReturn([
            'id' => 'switch-account',
            'name' => 'Managed account',
            'tree' => ['switch-parent'],
            'is_reseller' => true,
            'superduper_admin' => false,
            'billing_mode' => 'normal',
            'descendants_count' => 2,
        ]);
        $gateway->shouldReceive('descendants')->once()->andReturn([
            [
                'id' => 'switch-child',
                'name' => 'Managed child',
                'realm' => 'child.example.test',
                'tree' => ['switch-parent', 'switch-account'],
                'parent_id' => 'switch-account',
                'descendants_count' => 0,
            ],
            [
                'id' => 'switch-unmanaged',
                'name' => 'Not imported',
                'realm' => 'unmanaged.example.test',
                'tree' => ['switch-parent', 'switch-account'],
                'parent_id' => 'switch-account',
                'descendants_count' => 0,
            ],
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync")
            ->assertOk();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/hierarchy");

        $response->assertOk()
            ->assertJsonPath('data.account.id', $account->id)
            ->assertJsonPath('data.account.is_reseller', true)
            ->assertJsonPath('data.parent.id', $parent->id)
            ->assertJsonPath('data.children.0.id', $child->id)
            ->assertJsonPath('data.coverage.switch_descendants_count', 2)
            ->assertJsonPath('data.coverage.projected_descendants_count', 1)
            ->assertJsonPath('data.coverage.unresolved_descendants_count', 1)
            ->assertJsonMissingPath('data.account.account_id')
            ->assertJsonMissingPath('data.account.switch_account_id');

        $this->assertDatabaseCount('switch_accounts', 3);
        $this->assertDatabaseHas('switch_accounts', [
            'account_id' => $account->getKey(),
            'parent_account_id' => $parent->getKey(),
            'parent_switch_account_id' => 'switch-parent',
            'is_reseller' => true,
            'descendants_count' => 2,
        ]);
        $this->assertDatabaseHas('switch_accounts', [
            'account_id' => $child->getKey(),
            'parent_account_id' => $account->getKey(),
            'parent_switch_account_id' => 'switch-account',
        ]);
    }

    public function test_reseller_status_uses_the_service_billing_reseller_without_exposing_switch_ids(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $billingReseller = SwitchAccount::factory()->for($organization)->create([
            'name' => 'Billing reseller',
            'is_reseller' => true,
        ]);
        $account = SwitchAccount::factory()->for($organization)->create();
        SwitchServiceSummary::factory()->for($account)->create([
            'billing_reseller_account_id' => $billingReseller->getKey(),
            'billing_reseller_switch_account_id' => $billingReseller->switch_account_id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/reseller");

        $response->assertOk()
            ->assertJsonPath('data.account.id', $account->id)
            ->assertJsonPath('data.billing_reseller.id', $billingReseller->id)
            ->assertJsonPath('data.billing_reseller_projected', true)
            ->assertJsonPath('data.mutations.promote.available', false)
            ->assertJsonPath('data.mutations.demote.available', false)
            ->assertJsonMissingPath('data.billing_reseller.switch_account_id')
            ->assertJsonMissingPath('data.billing_reseller.account_id');
    }

    public function test_account_operator_cannot_view_reseller_administration(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_operator']);
        $account = SwitchAccount::factory()->for($organization)->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/hierarchy")
            ->assertForbidden();
        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/reseller")
            ->assertForbidden();
    }
}
