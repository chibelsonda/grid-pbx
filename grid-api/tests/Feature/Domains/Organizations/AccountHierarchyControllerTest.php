<?php

namespace Tests\Feature\Domains\Organizations;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Models\SwitchServiceQuantity;
use App\Domains\Services\Models\SwitchServiceSummary;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
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
            ->assertJsonPath('data.account.service_projection.status', 'healthy')
            ->assertJsonPath(
                'data.account.service_projection.billing_reseller.id',
                $billingReseller->id,
            )
            ->assertJsonPath('data.mutations.promote.available', false)
            ->assertJsonPath('data.mutations.demote.available', false)
            ->assertJsonMissingPath('data.billing_reseller.switch_account_id')
            ->assertJsonMissingPath('data.billing_reseller.account_id');
    }

    public function test_hierarchy_includes_safe_descendant_service_ownership_and_projection_health(): void
    {
        $this->travelTo('2026-08-31 06:00:00');
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'reseller_administrator']);
        $reseller = SwitchAccount::factory()->for($organization)->create([
            'name' => 'Grid Reseller',
            'is_reseller' => true,
            'descendants_count' => 1,
        ]);
        $child = SwitchAccount::factory()->for($organization)->create([
            'parent_account_id' => $reseller->getKey(),
            'parent_switch_account_id' => $reseller->switch_account_id,
            'name' => 'Acme Child',
        ]);
        SwitchServiceSummary::factory()->for($child)->create([
            'billing_reseller_account_id' => $reseller->getKey(),
            'billing_reseller_switch_account_id' => $reseller->switch_account_id,
            'last_synced_at' => now()->subMinutes(10),
            'sync_status' => ProjectionStatus::Healthy,
        ]);
        SyncCheckpoint::query()->create([
            'switch_account_id' => $child->getKey(),
            'resource_type' => 'services',
            'status' => ProjectionStatus::Syncing,
            'last_successful_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$reseller->id}/hierarchy");

        $response->assertOk()
            ->assertJsonPath('data.descendants.0.id', $child->id)
            ->assertJsonPath('data.descendants.0.service_projection.status', 'syncing')
            ->assertJsonPath(
                'data.descendants.0.service_projection.last_successful_at',
                '2026-08-31T05:50:00+00:00',
            )
            ->assertJsonPath(
                'data.descendants.0.service_projection.billing_reseller.id',
                $reseller->id,
            )
            ->assertJsonPath(
                'data.descendants.0.service_projection.billing_reseller.name',
                'Grid Reseller',
            )
            ->assertJsonPath(
                'data.descendants.0.service_projection.billing_reseller_projected',
                true,
            )
            ->assertJsonMissingPath('data.descendants.0.service_projection.billing_reseller.account_id')
            ->assertJsonMissingPath('data.descendants.0.service_projection.billing_reseller.switch_account_id');
    }

    public function test_hierarchy_summarizes_scoped_services_and_reports_safe_demotion_preflight(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'reseller_administrator']);
        $reseller = SwitchAccount::factory()->for($organization)->create([
            'name' => 'Grid Reseller',
            'is_reseller' => true,
            'descendants_count' => 1,
        ]);
        $child = SwitchAccount::factory()->for($organization)->create([
            'parent_account_id' => $reseller->getKey(),
            'parent_switch_account_id' => $reseller->switch_account_id,
            'name' => 'Acme Child',
        ]);
        SwitchServiceSummary::factory()->for($reseller)->create([
            'due_today' => 12.50,
            'recurring_amount' => 40,
            'sync_status' => ProjectionStatus::Healthy,
        ]);
        SwitchServiceSummary::factory()->for($child)->create([
            'billing_reseller_account_id' => $reseller->getKey(),
            'billing_reseller_switch_account_id' => $reseller->switch_account_id,
            'due_today' => 7.25,
            'recurring_amount' => 15.50,
            'sync_status' => ProjectionStatus::Stale,
        ]);
        SwitchServiceQuantity::query()->create([
            'switch_account_id' => $reseller->getKey(),
            'scope' => 'account',
            'category' => 'devices',
            'item' => 'sip_device',
            'quantity' => 2,
        ]);
        SwitchServiceQuantity::query()->create([
            'switch_account_id' => $child->getKey(),
            'scope' => 'account',
            'category' => 'devices',
            'item' => 'sip_device',
            'quantity' => 3,
        ]);
        SwitchServiceQuantity::query()->create([
            'switch_account_id' => $child->getKey(),
            'scope' => 'cascade',
            'category' => 'users',
            'item' => 'user',
            'quantity' => 4,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$reseller->id}/hierarchy");

        $response->assertOk()
            ->assertJsonPath('data.portfolio.accounts.total', 2)
            ->assertJsonPath('data.portfolio.accounts.projected', 2)
            ->assertJsonPath('data.portfolio.accounts.healthy', 1)
            ->assertJsonPath('data.portfolio.accounts.attention', 1)
            ->assertJsonPath('data.portfolio.billing_ownership.projected', 2)
            ->assertJsonPath('data.portfolio.billing_ownership.unresolved', 0)
            ->assertJsonPath('data.portfolio.billing.due_today', 19.75)
            ->assertJsonPath('data.portfolio.billing.recurring_amount', 55.5)
            ->assertJsonPath('data.portfolio.quantities.0.scope', 'account')
            ->assertJsonPath('data.portfolio.quantities.0.category', 'devices')
            ->assertJsonPath('data.portfolio.quantities.0.item', 'sip_device')
            ->assertJsonPath('data.portfolio.quantities.0.quantity', 5)
            ->assertJsonPath('data.portfolio.quantities.1.scope', 'cascade')
            ->assertJsonPath('data.portfolio.quantities.1.quantity', 4)
            ->assertJsonPath('data.portfolio.warnings.0.code', 'service_projection_attention')
            ->assertJsonPath('data.portfolio.warnings.0.count', 1)
            ->assertJsonPath('data.portfolio.warnings.0.affected_accounts.0.id', $child->id)
            ->assertJsonPath('data.portfolio.warnings.0.affected_accounts.0.name', 'Acme Child')
            ->assertJsonPath(
                'data.portfolio.warnings.0.affected_accounts.0.service_projection_status',
                'stale',
            )
            ->assertJsonPath(
                'data.portfolio.warnings.0.guidance',
                'Synchronize services for each listed account. If an error remains, review the safe synchronization status and server logs.',
            )
            ->assertJsonPath('data.mutation_preflight.operation', 'demote')
            ->assertJsonPath('data.mutation_preflight.operationally_ready', false)
            ->assertJsonPath('data.mutation_preflight.mutation_available', false)
            ->assertJsonPath('data.mutation_preflight.checks.2.code', 'no_descendants')
            ->assertJsonPath('data.mutation_preflight.checks.2.passed', false)
            ->assertJsonPath('data.mutation_preflight.checks.2.affected_accounts.0.id', $child->id)
            ->assertJsonPath('data.mutation_preflight.checks.3.code', 'no_billing_dependents')
            ->assertJsonPath('data.mutation_preflight.checks.3.count', 1)
            ->assertJsonPath('data.mutation_preflight.checks.3.affected_accounts.0.id', $child->id)
            ->assertJsonPath('data.mutation_preflight.checks.4.code', 'platform_policy_available')
            ->assertJsonPath('data.mutation_preflight.checks.4.passed', false)
            ->assertJsonPath('data.mutation_preflight.checks.4.affected_accounts', [])
            ->assertJsonMissingPath('data.portfolio.account_id')
            ->assertJsonMissingPath(
                'data.portfolio.warnings.0.affected_accounts.0.switch_account_id',
            )
            ->assertJsonMissingPath(
                'data.mutation_preflight.checks.3.affected_accounts.0.account_id',
            )
            ->assertJsonMissingPath('data.mutation_preflight.switch_account_id');
    }

    public function test_promotion_preflight_can_be_operationally_ready_without_enabling_mutation(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create([
            'is_reseller' => false,
            'descendants_count' => 0,
        ]);
        SwitchServiceSummary::factory()->for($account)->create([
            'billing_reseller_account_id' => null,
            'billing_reseller_switch_account_id' => null,
            'sync_status' => ProjectionStatus::Healthy,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/hierarchy");

        $response->assertOk()
            ->assertJsonPath('data.mutation_preflight.operation', 'promote')
            ->assertJsonPath('data.mutation_preflight.operationally_ready', true)
            ->assertJsonPath('data.mutation_preflight.mutation_available', false)
            ->assertJsonPath('data.mutation_preflight.checks.2.code', 'parent_projected')
            ->assertJsonPath('data.mutation_preflight.checks.2.passed', true)
            ->assertJsonPath('data.mutation_preflight.checks.3.code', 'billing_ownership_projected')
            ->assertJsonPath('data.mutation_preflight.checks.3.passed', true)
            ->assertJsonPath('data.mutation_preflight.checks.4.code', 'platform_policy_available')
            ->assertJsonPath('data.mutation_preflight.checks.4.passed', false);
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
