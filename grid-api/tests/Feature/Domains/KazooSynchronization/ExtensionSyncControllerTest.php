<?php

namespace Tests\Feature\Domains\KazooSynchronization;

use App\Domains\IdentityAccess\Infrastructure\Models\User;
use App\Domains\KazooSynchronization\Application\Jobs\SyncKazooExtensionsJob;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use App\Domains\Organizations\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExtensionSyncControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_queues_a_sync_run_for_an_accessible_account(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_operator']);
        $account = KazooAccount::factory()->for($organization)->create();

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->getKey()}/sync/extensions")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'queued');

        $this->assertDatabaseHas('kazoo_sync_runs', [
            'kazoo_account_id' => $account->getKey(),
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('kazoo_sync_checkpoints', [
            'kazoo_account_id' => $account->getKey(),
            'status' => 'syncing',
        ]);
        Queue::assertPushed(SyncKazooExtensionsJob::class);
    }

    public function test_it_reuses_an_active_sync_instead_of_creating_an_orphaned_run(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_operator']);
        $account = KazooAccount::factory()->for($organization)->create();

        $first = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->getKey()}/sync/extensions")
            ->assertAccepted()
            ->json('data.id');
        $second = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->getKey()}/sync/extensions")
            ->assertAccepted()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('kazoo_sync_runs', 1);
        Queue::assertPushed(SyncKazooExtensionsJob::class, 1);
    }

    public function test_it_does_not_queue_a_sync_for_an_inaccessible_account(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $account = KazooAccount::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->getKey()}/sync/extensions")
            ->assertNotFound();

        Queue::assertNothingPushed();
    }
}
