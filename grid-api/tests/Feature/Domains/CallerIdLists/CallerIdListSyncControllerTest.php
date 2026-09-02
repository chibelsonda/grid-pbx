<?php

namespace Tests\Feature\Domains\CallerIdLists;

use App\Domains\CallerIdLists\Jobs\SyncSwitchCallerIdListsJob;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CallerIdListSyncControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_starts_caller_id_list_sync_and_receives_public_status(): void
    {
        Queue::fake([SyncSwitchCallerIdListsJob::class]);
        [$user, $account] = $this->accessibleAccount();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/caller-id-lists");

        $response->assertAccepted()
            ->assertJsonPath('data.resource_type', 'caller_id_lists')
            ->assertJsonPath('data.status', SyncRunStatus::Queued->value)
            ->assertJsonMissingPath('data.switch_account_id');

        $runId = $response->json('data.id');
        $this->assertDatabaseHas('switch_sync_runs', [
            'id' => $runId,
            'switch_account_id' => $account->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'resource_type' => 'caller_id_lists',
            'status' => SyncRunStatus::Queued->value,
        ]);
        Queue::assertPushed(
            SyncSwitchCallerIdListsJob::class,
            fn (SyncSwitchCallerIdListsJob $job): bool => $job->switchAccountId === (string) $account->getKey(),
        );

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/sync/caller-id-lists/{$runId}")
            ->assertOk()
            ->assertJsonPath('data.id', $runId)
            ->assertJsonPath('data.resource_type', 'caller_id_lists')
            ->assertJsonMissingPath('data.switch_account_id');
    }

    public function test_read_only_user_cannot_start_caller_id_list_sync(): void
    {
        Queue::fake([SyncSwitchCallerIdListsJob::class]);
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/caller-id-lists")
            ->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('switch_sync_runs', 0);
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(
        OrganizationRole $role = OrganizationRole::AccountOperator,
    ): array {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
