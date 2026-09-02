<?php

namespace Tests\Feature\Domains\TemporalRouting;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleGateway;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleSetGateway;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TemporalRoutingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_accessible_user_lists_and_views_account_scoped_rules_and_rule_sets(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $account->update(['timezone' => 'UTC']);
        $rule = SwitchTemporalRule::factory()->for($account)->create([
            'name' => 'Business hours',
            'switch_resource_id' => 'private-rule-id',
            'switch_json' => ['private' => 'server-only'],
        ]);
        $set = SwitchTemporalRuleSet::factory()->for($account)->create([
            'name' => 'Office schedule',
            'switch_resource_id' => 'private-set-id',
            'switch_json' => ['private' => 'set-server-only'],
        ]);
        $set->rules()->create([
            'switch_temporal_rule_id' => $rule->getKey(),
            'switch_rule_resource_id' => 'private-rule-id',
            'position' => 0,
        ]);
        $foreignRule = SwitchTemporalRule::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/temporal-rules?search=Business")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rule->id)
            ->assertJsonPath('data.0.name', 'Business hours')
            ->assertJsonMissing(['private-rule-id', 'server-only']);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/temporal-rules/{$rule->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $rule->id)
            ->assertJsonMissingPath('data.switch_resource_id')
            ->assertJsonMissingPath('data.switch_json');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/temporal-rule-sets?search=Office")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $set->id)
            ->assertJsonPath('data.0.rule_count', 1)
            ->assertJsonMissing(['private-set-id', 'set-server-only']);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/temporal-rule-sets/{$set->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $set->id)
            ->assertJsonPath('data.rules.0.rule.id', $rule->id)
            ->assertJsonMissing(['private-rule-id', 'private-set-id']);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/temporal-rules/{$foreignRule->id}")
            ->assertNotFound();
    }

    public function test_operator_creates_a_rule_with_a_public_safe_projection(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchTemporalRuleGateway::class)->shouldReceive('create')->once()->withArgs(fn (SwitchAccount $received, array $data) => $received->is($account) && $data['weekdays'] === ['monday', 'tuesday'] && $data['start_date'] === '2026-09-01')->andReturn($this->ruleSnapshot());

        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/temporal-rules", $this->rulePayload());

        $response->assertCreated()->assertJsonPath('data.name', 'Business hours')->assertJsonPath('data.weekdays.0', 'monday')->assertJsonPath('data.start_date', '2026-09-01')->assertJsonMissingPath('data.temporal_rule_id')->assertJsonMissingPath('data.switch_resource_id')->assertJsonMissingPath('data.switch_json');
        $this->assertDatabaseHas('switch_temporal_rules', ['id' => $response->json('data.id'), 'switch_resource_id' => 'switch-rule-1']);
    }

    public function test_crud_rejects_status_overrides_and_preserves_an_existing_override_during_edit(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->mock(SwitchTemporalRuleGateway::class)->shouldNotReceive('create');
        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/temporal-rules", [...$this->rulePayload(), 'enabled' => true, 'flags' => ['external']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['enabled', 'flags']);

        $rule = SwitchTemporalRule::factory()->for($account)->create([
            'switch_resource_id' => 'switch-rule-1',
            'enabled' => false,
            'switch_json' => ['flags' => ['external']],
        ]);
        $this->mock(SwitchTemporalRuleGateway::class)
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $id, array $data): bool => $received->is($account)
                && $id === 'switch-rule-1'
                && ! array_key_exists('enabled', $data)
                && ! array_key_exists('flags', $data)
                && $data['name'] === 'Updated hours')
            ->andReturn([...$this->ruleSnapshot(), 'name' => 'Updated hours', 'enabled' => false, 'flags' => ['external']]);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/temporal-rules/{$rule->id}", [...$this->rulePayload(), 'name' => 'Updated hours'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated hours')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.effective_status.override', 'forced_inactive');
    }

    public function test_rule_schema_defaults_are_applied_when_optional_recurrence_fields_are_omitted(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchTemporalRuleGateway::class)
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (SwitchAccount $received, array $data): bool => $received->is($account)
                && $data['interval'] === 1
                && $data['days'] === []
                && $data['weekdays'] === [])
            ->andReturn(['id' => 'switch-rule-1', 'name' => 'One day', 'cycle' => 'date']);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/temporal-rules", ['name' => 'One day', 'cycle' => 'date'])
            ->assertCreated()
            ->assertJsonPath('data.interval', 1);
    }

    public function test_rule_set_edit_preserves_external_flags(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $rule = SwitchTemporalRule::factory()->for($account)->create(['switch_resource_id' => 'switch-rule-1']);
        $set = SwitchTemporalRuleSet::factory()->for($account)->create([
            'switch_resource_id' => 'switch-set-1',
            'switch_json' => ['flags' => ['external']],
        ]);
        $set->rules()->create(['switch_temporal_rule_id' => $rule->getKey(), 'switch_rule_resource_id' => 'switch-rule-1', 'position' => 0]);
        $this->mock(SwitchTemporalRuleSetGateway::class)
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $id, array $data): bool => $received->is($account)
                && $id === 'switch-set-1'
                && $data['switch_rule_ids'] === ['switch-rule-1']
                && ! array_key_exists('flags', $data))
            ->andReturn(['id' => 'switch-set-1', 'name' => 'Updated schedule', 'temporal_rules' => ['switch-rule-1'], 'flags' => ['external']]);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/temporal-rule-sets/{$set->id}", ['name' => 'Updated schedule', 'rule_ids' => [$rule->id]])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated schedule');
    }

    public function test_deleting_a_rule_set_removes_memberships_before_the_member_rule_is_deleted(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $rule = SwitchTemporalRule::factory()->for($account)->create(['switch_resource_id' => 'switch-rule-1']);
        $set = SwitchTemporalRuleSet::factory()->for($account)->create(['switch_resource_id' => 'switch-set-1']);
        $membership = $set->rules()->create(['switch_temporal_rule_id' => $rule->getKey(), 'switch_rule_resource_id' => 'switch-rule-1', 'position' => 0]);
        $this->mock(SwitchTemporalRuleSetGateway::class)
            ->shouldReceive('delete')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $id): bool => $received->is($account) && $id === 'switch-set-1');
        $this->mock(SwitchTemporalRuleGateway::class)
            ->shouldReceive('delete')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $id): bool => $received->is($account) && $id === 'switch-rule-1');

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/temporal-rule-sets/{$set->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('switch_temporal_rule_set_rules', ['temporal_rule_set_rule_id' => $membership->getKey()]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/temporal-rules/{$rule->id}")
            ->assertNoContent();
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

    public function test_rule_set_options_return_ordered_public_rule_references(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $later = SwitchTemporalRule::factory()->for($account)->create([
            'switch_resource_id' => 'private-rule-zulu',
            'name' => 'Zulu schedule',
            'cycle' => 'weekly',
        ]);
        $earlier = SwitchTemporalRule::factory()->for($account)->create([
            'switch_resource_id' => 'private-rule-alpha',
            'name' => 'Alpha schedule',
            'cycle' => 'monthly',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/temporal-rule-sets/options")
            ->assertOk()
            ->assertJsonPath('data.rules.0.id', $earlier->id)
            ->assertJsonPath('data.rules.0.label', 'Alpha schedule')
            ->assertJsonPath('data.rules.0.detail', 'monthly')
            ->assertJsonPath('data.rules.1.id', $later->id)
            ->assertJsonMissing(['private-rule-alpha', 'private-rule-zulu']);
    }

    public function test_read_only_user_cannot_mutate_and_cross_tenant_rules_are_rejected(): void
    {
        [$readOnly, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->mock(SwitchTemporalRuleGateway::class)->shouldNotReceive('create');
        $this->mock(SwitchTemporalRuleSetGateway::class)->shouldNotReceive('create');
        $this->actingAs($readOnly)->postJson("/api/v1/accounts/{$account->id}/temporal-rules", $this->rulePayload())->assertForbidden();
        [$operator, $managed] = $this->accessibleAccount();
        $foreign = SwitchTemporalRule::factory()->create();
        $this->actingAs($operator)->postJson("/api/v1/accounts/{$managed->id}/temporal-rule-sets", ['name' => 'Invalid', 'rule_ids' => [$foreign->id]])->assertUnprocessable()->assertJsonValidationErrors('rule_ids');
    }

    private function rulePayload(): array
    {
        return ['name' => 'Business hours', 'cycle' => 'weekly', 'interval' => 1, 'start_date' => '2026-09-01', 'time_window_start' => 32400, 'time_window_stop' => 61200, 'days' => [], 'weekdays' => ['monday', 'tuesday'], 'month' => null, 'ordinal' => null];
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
