<?php

namespace Tests\Feature\Domains\Directories;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Directories\Contracts\SwitchDirectoryGateway;
use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\Directories\Services\DirectorySynchronizationService;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DirectorySynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_projects_members_redacts_switch_json_and_removes_missing_directories(): void
    {
        $account = SwitchAccount::factory()->create();
        $user = User::factory()->create();
        $extension = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        $memberCallflow = SwitchCallflow::factory()->for($account)->for($extension, 'extension')->create(['switch_resource_id' => 'switch-callflow-1']);
        $route = SwitchCallflow::factory()->for($account)->create(['switch_json' => ['flow' => ['module' => 'directory', 'data' => ['id' => 'switch-directory-1'], 'children' => []]]]);
        $missing = SwitchDirectory::factory()->for($account)->create(['switch_resource_id' => 'missing']);
        $run = $account->syncRuns()->create(['requested_by_user_id' => $user->getKey(), 'resource_type' => 'directories', 'status' => SyncRunStatus::Queued]);
        $this->mock(SwitchDirectoryGateway::class)->shouldReceive('all')->once()->andReturn((function (): \Generator {
            yield ['id' => 'switch-directory-1', 'name' => 'People', 'users' => [['user_id' => 'switch-user-1', 'callflow_id' => 'switch-callflow-1']], 'sip' => ['password' => 'hidden']];
        })());

        $this->app->make(DirectorySynchronizationService::class)->handle($run);

        $directory = SwitchDirectory::query()->where('switch_resource_id', 'switch-directory-1')->firstOrFail();
        $this->assertSame('[REDACTED]', $directory->switch_json['sip']['password']);
        $this->assertSame($extension->getKey(), $directory->members()->value('switch_extension_id'));
        $this->assertSame($memberCallflow->getKey(), $directory->members()->value('switch_callflow_id'));
        $this->assertSame($directory->id, $route->fresh()->flow_structure['target']['id']);
        $this->assertSoftDeleted($missing);
        $this->assertDatabaseHas('switch_sync_runs', ['sync_run_id' => $run->getKey(), 'status' => 'succeeded', 'processed_count' => 1]);
    }
}
