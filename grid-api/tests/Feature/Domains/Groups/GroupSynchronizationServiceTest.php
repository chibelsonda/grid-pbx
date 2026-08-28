<?php

namespace Tests\Feature\Domains\Groups;

use App\Domains\Groups\Contracts\SwitchGroupGateway;
use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\Groups\Services\GroupSynchronizationService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GroupSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_projects_groups_and_reconciles_nested_group_references_after_all_upserts(): void
    {
        $account = SwitchAccount::factory()->create();
        $user = User::factory()->create();
        $missing = SwitchGroup::factory()->for($account)->create(['switch_resource_id' => 'missing']);
        $run = $account->syncRuns()->create(['requested_by_user_id' => $user->getKey(), 'resource_type' => 'groups', 'status' => SyncRunStatus::Queued]);
        $this->mock(SwitchGroupGateway::class)->shouldReceive('all')->once()->andReturn((function (): \Generator {
            yield ['id' => 'switch-parent', 'name' => 'All support', 'endpoints' => ['switch-child' => ['type' => 'group', 'weight' => 1]]];
            yield ['id' => 'switch-child', 'name' => 'Level one', 'endpoints' => []];
        })());

        $this->app->make(GroupSynchronizationService::class)->handle($run);

        $parent = SwitchGroup::query()->where('switch_resource_id', 'switch-parent')->firstOrFail();
        $child = SwitchGroup::query()->where('switch_resource_id', 'switch-child')->firstOrFail();
        $this->assertSame($child->getKey(), $parent->members()->value('nested_switch_group_id'));
        $this->assertSoftDeleted($missing);
        $this->assertDatabaseHas('switch_sync_checkpoints', ['switch_account_id' => $account->getKey(), 'resource_type' => 'groups', 'status' => 'healthy']);
    }
}
