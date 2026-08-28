<?php

namespace Tests\Feature\Domains\TemporalRouting;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleGateway;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleSetGateway;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TemporalRoutingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_creates_a_rule_with_a_public_safe_projection(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchTemporalRuleGateway::class)->shouldReceive('create')->once()->withArgs(fn (SwitchAccount $received, array $data) => $received->is($account) && $data['weekdays'] === ['monday', 'tuesday'] && $data['start_date'] === '2026-09-01')->andReturn($this->ruleSnapshot());

        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/temporal-rules", $this->rulePayload());

        $response->assertCreated()->assertJsonPath('data.name', 'Business hours')->assertJsonPath('data.weekdays.0', 'monday')->assertJsonPath('data.start_date', '2026-09-01')->assertJsonMissingPath('data.temporal_rule_id')->assertJsonMissingPath('data.switch_resource_id')->assertJsonMissingPath('data.switch_json');
        $this->assertDatabaseHas('switch_temporal_rules', ['id' => $response->json('data.id'), 'switch_resource_id' => 'switch-rule-1']);
    }

    public function test_operator_creates_an_ordered_rule_set_using_public_rule_ids(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $first = SwitchTemporalRule::factory()->for($account)->create(['switch_resource_id' => 'switch-rule-1', 'name' => 'Business hours']);
        $second = SwitchTemporalRule::factory()->for($account)->create(['switch_resource_id' => 'switch-rule-2', 'name' => 'Holiday']);
        $this->mock(SwitchTemporalRuleSetGateway::class)->shouldReceive('create')->once()->withArgs(fn (SwitchAccount $received, array $data) => $received->is($account) && $data['switch_rule_ids'] === ['switch-rule-2', 'switch-rule-1'])->andReturn(['id' => 'switch-set-1', 'name' => 'Office schedule', 'temporal_rules' => ['switch-rule-2', 'switch-rule-1']]);

        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/temporal-rule-sets", ['name' => 'Office schedule', 'rule_ids' => [$second->id, $first->id]]);

        $response->assertCreated()->assertJsonPath('data.rules.0.rule.id', $second->id)->assertJsonPath('data.rules.1.rule.id', $first->id)->assertJsonMissingPath('data.switch_resource_id');
        $this->assertDatabaseHas('switch_temporal_rule_set_rules', ['switch_rule_resource_id' => 'switch-rule-2', 'position' => 0]);
    }

    public function test_read_only_user_cannot_mutate_and_cross_tenant_rules_are_rejected(): void
    {
        [$readOnly, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->actingAs($readOnly)->postJson("/api/v1/accounts/{$account->id}/temporal-rules", $this->rulePayload())->assertForbidden();
        [$operator, $managed] = $this->accessibleAccount();
        $foreign = SwitchTemporalRule::factory()->create();
        $this->actingAs($operator)->postJson("/api/v1/accounts/{$managed->id}/temporal-rule-sets", ['name' => 'Invalid', 'rule_ids' => [$foreign->id]])->assertUnprocessable()->assertJsonValidationErrors('rule_ids');
    }

    private function rulePayload(): array
    {
        return ['name' => 'Business hours', 'cycle' => 'weekly', 'interval' => 1, 'start_date' => '2026-09-01', 'time_window_start' => 32400, 'time_window_stop' => 61200, 'enabled' => true, 'days' => [], 'weekdays' => ['monday', 'tuesday'], 'month' => null, 'ordinal' => null];
    }

    private function ruleSnapshot(): array
    {
        return ['id' => 'switch-rule-1', 'name' => 'Business hours', 'cycle' => 'weekly', 'interval' => 1, 'start_date' => 63955440000, 'time_window_start' => 32400, 'time_window_stop' => 61200, 'enabled' => true, 'days' => [], 'wdays' => ['monday', 'tuesday']];
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(OrganizationRole $role = OrganizationRole::AccountOperator): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
