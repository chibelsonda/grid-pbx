<?php

namespace Tests\Feature\Domains\Blacklists;

use App\Domains\Blacklists\Contracts\SwitchBlacklistGateway;
use App\Domains\Blacklists\Models\SwitchBlacklist;
use App\Domains\Blacklists\Services\BlacklistSynchronizationService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BlacklistSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_projects_active_state_and_number_metadata_then_soft_deletes_missing_records(): void
    {
        $account = SwitchAccount::factory()->create(); $missing = SwitchBlacklist::factory()->for($account)->create(['switch_resource_id' => 'missing']);
        $run = $account->syncRuns()->create(['requested_by_user_id' => User::factory()->create()->getKey(), 'resource_type' => 'blacklists', 'status' => SyncRunStatus::Queued]);
        $gateway = $this->mock(SwitchBlacklistGateway::class);
        $gateway->shouldReceive('activeIds')->once()->andReturn(['blacklist-1']);
        $gateway->shouldReceive('all')->once()->andReturn((function (): \Generator { yield ['id' => 'blacklist-1', 'name' => 'Spam', 'numbers' => ['+15550001000' => ['note' => 'Robocall']], 'should_block_anonymous' => true]; })());

        $this->app->make(BlacklistSynchronizationService::class)->handle($run);

        $this->assertDatabaseHas('switch_blacklists', ['switch_account_id' => $account->getKey(), 'switch_resource_id' => 'blacklist-1', 'is_active' => true]);
        $this->assertDatabaseHas('switch_blacklist_entries', ['number' => '+15550001000', 'metadata' => json_encode(['note' => 'Robocall'])]);
        $this->assertSoftDeleted($missing);
        $this->assertDatabaseHas('switch_sync_checkpoints', ['switch_account_id' => $account->getKey(), 'resource_type' => 'blacklists', 'status' => 'healthy']);
    }
}
