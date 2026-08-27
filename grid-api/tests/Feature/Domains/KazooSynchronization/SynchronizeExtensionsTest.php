<?php

namespace Tests\Feature\Domains\KazooSynchronization;

use App\Domains\Extensions\Infrastructure\Models\KazooExtension;
use App\Domains\KazooSynchronization\Application\Actions\SynchronizeExtensions;
use App\Domains\KazooSynchronization\Application\Contracts\KazooUserGateway;
use App\Domains\KazooSynchronization\Domain\SyncRunStatus;
use App\Domains\KazooSynchronization\Infrastructure\Models\SyncRun;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SynchronizeExtensionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_projects_kazoo_users_and_soft_deletes_missing_records(): void
    {
        $account = KazooAccount::factory()->create();
        $missing = KazooExtension::factory()->for($account)->create([
            'kazoo_resource_id' => 'removed-user',
        ]);
        $run = SyncRun::query()->create([
            'kazoo_account_id' => $account->getKey(),
            'resource_type' => 'extensions',
            'status' => SyncRunStatus::Queued,
        ]);

        $this->app->instance(KazooUserGateway::class, new class implements KazooUserGateway
        {
            public function users(KazooAccount $account): iterable
            {
                yield [
                    'id' => 'kazoo-user-1',
                    'username' => 'alice',
                    'first_name' => 'Alice',
                    'last_name' => 'Operator',
                    'email' => 'alice@example.test',
                    'caller_id' => ['internal' => ['number' => '1001']],
                    'timezone' => 'Asia/Manila',
                    'enabled' => true,
                    '_rev' => '2-test',
                ];
            }
        });

        $this->app->make(SynchronizeExtensions::class)->handle($run);

        $this->assertDatabaseHas('kazoo_extensions', [
            'kazoo_account_id' => $account->getKey(),
            'kazoo_resource_id' => 'kazoo-user-1',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
            'sync_status' => 'healthy',
        ]);
        $this->assertSoftDeleted($missing);
        $this->assertDatabaseHas('kazoo_sync_runs', [
            'id' => $run->getKey(),
            'status' => 'succeeded',
            'processed_count' => 1,
            'deleted_count' => 1,
        ]);
        $this->assertDatabaseHas('kazoo_sync_checkpoints', [
            'kazoo_account_id' => $account->getKey(),
            'status' => 'healthy',
        ]);

    }
}
