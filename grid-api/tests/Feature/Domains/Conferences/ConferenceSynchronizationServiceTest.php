<?php

namespace Tests\Feature\Domains\Conferences;

use App\Domains\Conferences\Contracts\SwitchConferenceGateway;
use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\Conferences\Services\ConferenceSynchronizationService;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConferenceSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_projects_safe_conference_configuration_runtime_and_soft_deletes_missing_rows(): void
    {
        $account = SwitchAccount::factory()->create();
        $owner = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        $missing = SwitchConference::factory()->for($account)->create(['switch_resource_id' => 'missing']);
        $run = $account->syncRuns()->create(['requested_by_user_id' => User::factory()->create()->getKey(), 'resource_type' => 'conferences', 'status' => SyncRunStatus::Queued]);
        $this->mock(SwitchConferenceGateway::class)->shouldReceive('all')->once()->andReturn((function (): \Generator {
            yield [
                'id' => 'switch-conference-1', 'name' => 'Daily standup', 'owner_id' => 'switch-user-1',
                'conference_numbers' => ['7000'], 'member' => ['numbers' => ['7001'], 'pins' => ['1234']],
                'moderator' => ['numbers' => ['7099'], 'pins' => ['9876']],
                '_read_only' => ['members' => 3, 'moderators' => 1, 'duration' => 120, 'is_locked' => true],
            ];
        })());

        $this->app->make(ConferenceSynchronizationService::class)->handle($run);

        $conference = SwitchConference::query()->where('switch_resource_id', 'switch-conference-1')->firstOrFail();
        $this->assertSame($owner->getKey(), $conference->owner_extension_id); $this->assertSame(3, $conference->active_members); $this->assertTrue($conference->is_locked);
        $this->assertSame('[REDACTED]', $conference->switch_json['member']['pins']); $this->assertSame('[REDACTED]', $conference->switch_json['moderator']['pins']);
        $this->assertSoftDeleted($missing); $this->assertDatabaseHas('switch_sync_checkpoints', ['switch_account_id' => $account->getKey(), 'resource_type' => 'conferences', 'status' => 'healthy']);
    }
}
