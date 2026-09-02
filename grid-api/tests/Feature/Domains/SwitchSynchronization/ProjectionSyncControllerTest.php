<?php

namespace Tests\Feature\Domains\SwitchSynchronization;

use App\Domains\Blacklists\Jobs\SyncSwitchBlacklistsJob;
use App\Domains\Conferences\Jobs\SyncSwitchConferencesJob;
use App\Domains\Directories\Jobs\SyncSwitchDirectoriesJob;
use App\Domains\Faxes\Jobs\SyncSwitchFaxesJob;
use App\Domains\Groups\Jobs\SyncSwitchGroupsJob;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Jobs\SyncSwitchMediaJob;
use App\Domains\Menus\Jobs\SyncSwitchMenusJob;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Jobs\SyncSwitchQueuesJob;
use App\Domains\Recordings\Jobs\SyncSwitchRecordingsJob;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectionSyncControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @param class-string $jobClass */
    #[DataProvider('syncResources')]
    public function test_administrator_starts_and_reads_an_account_projection_sync(
        string $routeSegment,
        string $resourceType,
        string $jobClass,
    ): void {
        Queue::fake([$jobClass]);
        [$user, $account] = $this->accessibleAccount();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/{$routeSegment}");

        $response->assertAccepted()
            ->assertJsonPath('data.resource_type', $resourceType)
            ->assertJsonPath('data.status', SyncRunStatus::Queued->value)
            ->assertJsonMissingPath('data.switch_account_id');

        $runId = $response->json('data.id');
        $this->assertDatabaseHas('switch_sync_runs', [
            'id' => $runId,
            'switch_account_id' => $account->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'resource_type' => $resourceType,
            'status' => SyncRunStatus::Queued->value,
        ]);
        Queue::assertPushed($jobClass);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/sync/{$routeSegment}/{$runId}")
            ->assertOk()
            ->assertJsonPath('data.id', $runId)
            ->assertJsonPath('data.resource_type', $resourceType)
            ->assertJsonMissingPath('data.switch_account_id');
    }

    /** @return iterable<string, array{string, string, class-string}> */
    public static function syncResources(): iterable
    {
        yield 'blacklists' => ['blacklists', 'blacklists', SyncSwitchBlacklistsJob::class];
        yield 'conferences' => ['conferences', 'conferences', SyncSwitchConferencesJob::class];
        yield 'directories' => ['directories', 'directories', SyncSwitchDirectoriesJob::class];
        yield 'faxes' => ['faxes', 'faxes', SyncSwitchFaxesJob::class];
        yield 'groups' => ['groups', 'groups', SyncSwitchGroupsJob::class];
        yield 'media' => ['media', 'media', SyncSwitchMediaJob::class];
        yield 'menus' => ['menus', 'menus', SyncSwitchMenusJob::class];
        yield 'queues' => ['queues', 'queues', SyncSwitchQueuesJob::class];
        yield 'recordings' => ['recordings', 'recordings', SyncSwitchRecordingsJob::class];
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, [
            'role' => OrganizationRole::AccountAdministrator->value,
        ]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
