<?php

namespace Tests\Feature\Domains\Services;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Jobs\SyncSwitchServicesJob;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServiceSyncControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reseller_administrator_starts_service_sync_for_projected_descendant(): void
    {
        Queue::fake([SyncSwitchServicesJob::class]);
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'reseller_administrator']);
        $reseller = SwitchAccount::factory()->for($organization)->create(['is_reseller' => true]);
        $child = SwitchAccount::factory()->for($organization)->create([
            'parent_account_id' => $reseller->getKey(),
            'parent_switch_account_id' => $reseller->switch_account_id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$child->id}/sync/services");

        $response->assertAccepted()
            ->assertJsonPath('data.resource_type', 'services')
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonMissingPath('data.sync_run_id')
            ->assertJsonMissingPath('data.switch_account_id');
        $this->assertDatabaseHas('switch_sync_runs', [
            'id' => $response->json('data.id'),
            'switch_account_id' => $child->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'resource_type' => 'services',
            'status' => 'queued',
        ]);
        Queue::assertPushed(
            SyncSwitchServicesJob::class,
            fn (SyncSwitchServicesJob $job): bool => $job->switchAccountId === (string) $child->getKey(),
        );
    }

    public function test_user_cannot_start_service_sync_for_account_outside_organization(): void
    {
        Queue::fake([SyncSwitchServicesJob::class]);
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'reseller_administrator']);
        $otherAccount = SwitchAccount::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$otherAccount->id}/sync/services")
            ->assertNotFound();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('switch_sync_runs', 0);
    }

    public function test_failed_sync_status_hides_internal_error_details(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create();
        $run = SyncRun::query()->create([
            'switch_account_id' => $account->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'resource_type' => 'services',
            'status' => SyncRunStatus::Failed,
            'error_code' => 'RuntimeException',
            'error_message' => 'SQLSTATE private-db.internal password=secret',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/sync/services/{$run->id}");

        $response->assertOk()
            ->assertJsonPath(
                'data.error_message',
                'Synchronization failed. Try again or contact an administrator.',
            )
            ->assertDontSee('private-db.internal')
            ->assertDontSee('password=secret');
    }
}
