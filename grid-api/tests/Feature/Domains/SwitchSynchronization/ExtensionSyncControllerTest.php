<?php

namespace Tests\Feature\Domains\SwitchSynchronization;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Jobs\SyncSwitchExtensionsJob;
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
        $account = SwitchAccount::factory()->for($organization)->create();

        $runId = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/extensions")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonMissingPath('data.sync_run_id')
            ->json('data.id');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/sync/extensions/{$runId}")
            ->assertOk()
            ->assertJsonPath('data.id', $runId)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonMissingPath('data.sync_run_id');

        $this->assertDatabaseHas('switch_sync_runs', [
            'switch_account_id' => $account->getKey(),
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('switch_sync_checkpoints', [
            'switch_account_id' => $account->getKey(),
            'status' => 'syncing',
        ]);
        Queue::assertPushed(SyncSwitchExtensionsJob::class);
    }

    public function test_it_reuses_an_active_sync_instead_of_creating_an_orphaned_run(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_operator']);
        $account = SwitchAccount::factory()->for($organization)->create();

        $first = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/extensions")
            ->assertAccepted()
            ->json('data.id');
        $second = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/extensions")
            ->assertAccepted()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('switch_sync_runs', 1);
        Queue::assertPushed(SyncSwitchExtensionsJob::class, 1);
    }

    public function test_it_does_not_queue_a_sync_for_an_inaccessible_account(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $account = SwitchAccount::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/extensions")
            ->assertNotFound();

        Queue::assertNothingPushed();
    }
}
