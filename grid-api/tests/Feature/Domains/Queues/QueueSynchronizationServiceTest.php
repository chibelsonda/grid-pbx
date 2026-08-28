<?php

namespace Tests\Feature\Domains\Queues;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchQueueGateway;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\Queues\Services\QueueSynchronizationService;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class QueueSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_projects_queue_rosters_and_soft_deletes_missing_queues(): void
    {
        $account = SwitchAccount::factory()->create();
        $agent = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        $missing = SwitchQueue::factory()->for($account)->create(['switch_resource_id' => 'missing']);
        $user = User::factory()->create();
        $run = $account->syncRuns()->create(['requested_by_user_id' => $user->getKey(), 'resource_type' => 'queues', 'status' => SyncRunStatus::Queued]);
        $this->mock(SwitchQueueGateway::class)->shouldReceive('all')->once()->andReturn((function (): \Generator {
            yield [
                'id' => 'switch-queue-1', 'name' => 'Support', 'strategy' => 'most_idle',
                'agents' => ['switch-user-1'], 'record_caller' => true,
            ];
        })());

        $this->app->make(QueueSynchronizationService::class)->handle($run);

        $queue = SwitchQueue::query()->where('switch_resource_id', 'switch-queue-1')->firstOrFail();
        $this->assertSame($agent->getKey(), $queue->agents()->value('switch_extension_id'));
        $this->assertTrue($queue->record_caller);
        $this->assertSoftDeleted($missing);
        $this->assertDatabaseHas('switch_sync_checkpoints', ['switch_account_id' => $account->getKey(), 'resource_type' => 'queues', 'status' => 'healthy']);
    }
}
