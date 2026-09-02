<?php

namespace Tests\Feature\Domains\TemporalRouting;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleGateway;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TemporalOperationalControlControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_can_force_a_rule_active_and_project_its_effective_status(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $rule = SwitchTemporalRule::factory()->for($account)->create([
            'switch_resource_id' => 'switch-rule-1',
            'enabled' => null,
        ]);
        $this->mock(SwitchTemporalRuleGateway::class)
            ->shouldReceive('setOverride')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $id, ?bool $enabled): bool => $received->is($account) && $id === 'switch-rule-1' && $enabled === true)
            ->andReturn($this->snapshot('switch-rule-1', true));

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/temporal-rules/{$rule->id}/commands", ['action' => 'enable'])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.effective_status.state', 'active')
            ->assertJsonPath('data.effective_status.override', 'forced_active');

        $this->assertDatabaseHas('audit_logs', ['action' => 'temporal_rule.enable', 'outcome' => 'succeeded']);
    }

    public function test_reset_uses_a_null_override_and_returns_to_schedule_evaluation(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $rule = SwitchTemporalRule::factory()->for($account)->create([
            'switch_resource_id' => 'switch-rule-1',
            'enabled' => false,
        ]);
        $this->mock(SwitchTemporalRuleGateway::class)
            ->shouldReceive('setOverride')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $id, ?bool $enabled): bool => $received->is($account) && $id === 'switch-rule-1' && $enabled === null)
            ->andReturn($this->snapshot('switch-rule-1', null));

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/temporal-rules/{$rule->id}/commands", ['action' => 'reset'])
            ->assertOk()
            ->assertJsonPath('data.enabled', null)
            ->assertJsonPath('data.effective_status.override', 'scheduled');
    }

    public function test_rule_set_control_updates_every_resolved_member(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $first = SwitchTemporalRule::factory()->for($account)->create(['switch_resource_id' => 'switch-rule-1', 'enabled' => null]);
        $second = SwitchTemporalRule::factory()->for($account)->create(['switch_resource_id' => 'switch-rule-2', 'enabled' => null]);
        $set = SwitchTemporalRuleSet::factory()->for($account)->create(['switch_resource_id' => 'switch-set-1']);
        $set->rules()->createMany([
            ['switch_temporal_rule_id' => $first->getKey(), 'switch_rule_resource_id' => 'switch-rule-1', 'position' => 0],
            ['switch_temporal_rule_id' => $second->getKey(), 'switch_rule_resource_id' => 'switch-rule-2', 'position' => 1],
        ]);
        $gateway = $this->mock(SwitchTemporalRuleGateway::class);
        $gateway->shouldReceive('setOverride')->once()->withArgs(fn (SwitchAccount $received, string $id, ?bool $enabled): bool => $received->is($account) && $id === 'switch-rule-1' && $enabled === false)->andReturn($this->snapshot('switch-rule-1', false));
        $gateway->shouldReceive('setOverride')->once()->withArgs(fn (SwitchAccount $received, string $id, ?bool $enabled): bool => $received->is($account) && $id === 'switch-rule-2' && $enabled === false)->andReturn($this->snapshot('switch-rule-2', false));

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/temporal-rule-sets/{$set->id}/commands", ['action' => 'disable'])
            ->assertOk()
            ->assertJsonPath('data.effective_status.state', 'inactive')
            ->assertJsonPath('data.effective_status.override', 'forced_inactive')
            ->assertJsonPath('data.effective_status.rule_count', 2);

        $this->assertDatabaseHas('audit_logs', ['action' => 'temporal_rule_set.disable', 'outcome' => 'succeeded']);
    }

    public function test_read_only_user_cannot_run_operational_commands(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $rule = SwitchTemporalRule::factory()->for($account)->create();
        $this->mock(SwitchTemporalRuleGateway::class)->shouldNotReceive('setOverride');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/temporal-rules/{$rule->id}/commands", ['action' => 'disable'])
            ->assertForbidden();
    }

    public function test_operational_command_requires_an_action(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $rule = SwitchTemporalRule::factory()->for($account)->create();
        $this->mock(SwitchTemporalRuleGateway::class)->shouldNotReceive('setOverride');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/temporal-rules/{$rule->id}/commands")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');
    }

    public function test_operational_command_rejects_an_unknown_action(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $rule = SwitchTemporalRule::factory()->for($account)->create();
        $this->mock(SwitchTemporalRuleGateway::class)->shouldNotReceive('setOverride');

        $this->actingAs($user)
            ->postJson(
                "/api/v1/accounts/{$account->id}/temporal-rules/{$rule->id}/commands",
                ['action' => 'restart'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');
    }

    /** @return array<string, mixed> */
    private function snapshot(string $id, ?bool $enabled): array
    {
        $snapshot = [
            'id' => $id,
            'name' => 'Business hours',
            'cycle' => 'weekly',
            'interval' => 1,
            'start_date' => 63955440000,
            'time_window_start' => 32400,
            'time_window_stop' => 61200,
            'wdays' => ['monday', 'tuesday'],
        ];

        if ($enabled !== null) {
            $snapshot['enabled'] = $enabled;
        }

        return $snapshot;
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(OrganizationRole $role = OrganizationRole::AccountOperator): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create(['timezone' => 'UTC'])];
    }
}
