<?php

namespace Tests\Feature\Domains\TemporalRouting;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleGateway;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleSetGateway;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use App\Domains\TemporalRouting\Services\TemporalRoutingSynchronizationService;
use App\Domains\TemporalRouting\Services\TemporalRuleSetProjectionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TemporalRoutingSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_projects_rules_then_sets_and_soft_deletes_missing_records(): void
    {
        $account = SwitchAccount::factory()->create();
        $missingRule = SwitchTemporalRule::factory()->for($account)->create(['switch_resource_id' => 'missing-rule']);
        $missingSet = SwitchTemporalRuleSet::factory()->for($account)->create(['switch_resource_id' => 'missing-set']);
        $run = $account->syncRuns()->create(['requested_by_user_id' => User::factory()->create()->getKey(), 'resource_type' => 'temporal_routing', 'status' => SyncRunStatus::Queued]);
        $this->mock(SwitchTemporalRuleGateway::class)->shouldReceive('all')->once()->andReturn((function (): \Generator {
            yield ['id' => 'switch-rule-1', 'name' => 'Business hours', 'cycle' => 'weekly', 'wdays' => ['monday'], 'time_window_start' => 32400, 'time_window_stop' => 61200];
        })());
        $this->mock(SwitchTemporalRuleSetGateway::class)->shouldReceive('all')->once()->andReturn((function (): \Generator {
            yield ['id' => 'switch-set-1', 'name' => 'Office schedule', 'temporal_rules' => ['switch-rule-1']];
        })());

        $this->app->make(TemporalRoutingSynchronizationService::class)->handle($run);

        $set = SwitchTemporalRuleSet::query()->where('switch_resource_id', 'switch-set-1')->firstOrFail();
        $this->assertNotNull($set->rules()->value('switch_temporal_rule_id'));
        $this->assertSoftDeleted($missingRule);
        $this->assertSoftDeleted($missingSet);
        $this->assertDatabaseHas('switch_sync_checkpoints', ['switch_account_id' => $account->getKey(), 'resource_type' => 'temporal_routing', 'status' => 'healthy']);
    }

    public function test_rule_set_projection_reorders_existing_members_without_position_conflicts(): void
    {
        $account = SwitchAccount::factory()->create();
        $first = SwitchTemporalRule::factory()->for($account)->create(['switch_resource_id' => 'rule-1']);
        $second = SwitchTemporalRule::factory()->for($account)->create(['switch_resource_id' => 'rule-2']);
        $set = SwitchTemporalRuleSet::factory()->for($account)->create(['switch_resource_id' => 'set-1']);
        $set->rules()->create(['switch_temporal_rule_id' => $first->getKey(), 'switch_rule_resource_id' => 'rule-1', 'position' => 0]);
        $set->rules()->create(['switch_temporal_rule_id' => $second->getKey(), 'switch_rule_resource_id' => 'rule-2', 'position' => 1]);

        $projected = $this->app->make(TemporalRuleSetProjectionService::class)->project($account, [
            'id' => 'set-1', 'name' => 'Reordered', 'temporal_rules' => ['rule-2', 'rule-1'],
        ]);

        $this->assertSame(['rule-2', 'rule-1'], $projected->rules->pluck('switch_rule_resource_id')->all());
    }
}
