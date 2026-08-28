<?php

namespace Tests\Feature\Domains\Queues;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchQueueGateway;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\Queues\Services\QueueMutationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class QueueMutationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_failed_roster_clear_restores_membership_and_preserves_the_queue(): void
    {
        $account = SwitchAccount::factory()->create();
        $actor = User::factory()->create();
        $agent = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        $queue = SwitchQueue::factory()->for($account)->create(['switch_resource_id' => 'switch-queue-1']);
        $queue->agents()->create(['switch_extension_id' => $agent->getKey(), 'switch_user_resource_id' => 'switch-user-1']);
        $gateway = $this->mock(SwitchQueueGateway::class);
        $gateway->shouldReceive('replaceRoster')->once()->withArgs(fn (SwitchAccount $received, string $queueId, array $agents): bool => $received->is($account) && $queueId === 'switch-queue-1' && $agents === [])
            ->andThrow(new RuntimeException('Roster response failed.'));
        $gateway->shouldReceive('replaceRoster')->once()->withArgs(fn (SwitchAccount $received, string $queueId, array $agents): bool => $received->is($account) && $queueId === 'switch-queue-1' && $agents === ['switch-user-1'])
            ->andReturn(['id' => 'switch-queue-1', 'name' => 'Support', 'agents' => ['switch-user-1']]);
        $gateway->shouldNotReceive('delete');

        $this->expectException(RuntimeException::class);

        try {
            $this->app->make(QueueMutationService::class)->delete($account, $queue, $actor);
        } finally {
            $this->assertNotSoftDeleted($queue);
        }
    }
}
