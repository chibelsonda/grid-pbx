<?php

namespace Tests\Feature\Domains\CallerIdLists;

use App\Domains\CallerIdLists\Contracts\SwitchCallerIdListGateway;
use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\CallerIdLists\Services\CallerIdListSynchronizationService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CallerIdListSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_projects_list_and_entry_payloads_then_removes_missing_lists(): void
    {
        $account = SwitchAccount::factory()->create();
        $missing = $account->callerIdLists()->create([
            'switch_resource_id' => 'missing-list',
            'name' => 'Old list',
        ]);
        $run = $account->syncRuns()->create([
            'requested_by_user_id' => User::factory()->create()->getKey(),
            'resource_type' => 'caller_id_lists',
            'status' => SyncRunStatus::Queued,
        ]);
        $gateway = $this->mock(SwitchCallerIdListGateway::class);
        $gateway->shouldReceive('all')->once()->andReturn((function (): \Generator {
            yield [
                'list' => [
                    'id' => 'list-1',
                    'name' => 'VIP callers',
                    'description' => 'Priority callers',
                    'org' => 'GridPBX',
                ],
                'entries' => [[
                    'id' => 'entry-1',
                    'list_id' => 'list-1',
                    'displayname' => 'Manila prefix',
                    'pattern' => '^\\+632',
                ]],
            ];
        })());

        $this->app->make(CallerIdListSynchronizationService::class)->handle($run);

        $projected = SwitchCallerIdList::query()->where('switch_resource_id', 'list-1')->firstOrFail();
        $this->assertSame('VIP callers', $projected->name);
        $this->assertSame('Priority callers', $projected->description);
        $this->assertSame('list-1', $projected->switch_json['id']);
        $this->assertDatabaseHas('switch_caller_id_list_entries', [
            'switch_caller_id_list_id' => $projected->getKey(),
            'switch_resource_id' => 'entry-1',
            'pattern' => '^\\+632',
        ]);
        $this->assertSame('entry-1', $projected->entries()->firstOrFail()->switch_json['id']);
        $this->assertSoftDeleted($missing);
        $this->assertDatabaseHas('switch_sync_checkpoints', [
            'switch_account_id' => $account->getKey(),
            'resource_type' => 'caller_id_lists',
            'status' => 'healthy',
        ]);
    }
}
