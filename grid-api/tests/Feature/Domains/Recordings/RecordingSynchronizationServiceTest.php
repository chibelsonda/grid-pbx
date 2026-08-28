<?php

namespace Tests\Feature\Domains\Recordings;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Recordings\Contracts\SwitchRecordingGateway;
use App\Domains\Recordings\Models\SwitchRecording;
use App\Domains\Recordings\Services\RecordingSynchronizationService;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RecordingSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_projects_full_redacted_metadata_and_reconciles_only_the_import_window(): void
    {
        config()->set('switch.recording_import_window_days', 31); $account = SwitchAccount::factory()->create();
        $missingRecent = SwitchRecording::factory()->for($account)->create(['switch_resource_id' => '202608-missing', 'started_at' => now()->subDay()]);
        $older = SwitchRecording::factory()->for($account)->create(['switch_resource_id' => '202605-older', 'started_at' => now()->subDays(100)]);
        $run = $account->syncRuns()->create(['requested_by_user_id' => User::factory()->create()->getKey(), 'resource_type' => 'recordings', 'status' => SyncRunStatus::Queued]);
        $this->mock(SwitchRecordingGateway::class)->shouldReceive('all')->once()->andReturn((function (): \Generator { yield ['switch_resource_id' => '202608-recording-1', 'owner_switch_resource_id' => null, 'call_id' => 'call-1', 'cdr_id' => null, 'interaction_id' => 'interaction-1', 'direction' => 'inbound', 'caller_id_number' => '+15550001000', 'callee_id_number' => '+15550002000', 'started_at_unix' => now()->subHour()->timestamp, 'duration_seconds' => 42, 'duration_milliseconds' => 42000, 'content_type' => 'audio/mpeg', 'content_length' => 1024, 'has_audio' => true, 'data' => ['id' => '202608-recording-1', 'url' => 'https://storage.test/audio?token=secret', 'custom_channel_vars' => ['Password' => 'do-not-store'], 'name' => 'Support call']]; })());

        $this->app->make(RecordingSynchronizationService::class)->handle($run);

        $recording = SwitchRecording::query()->where('switch_resource_id', '202608-recording-1')->firstOrFail();
        self::assertSame('[REDACTED]', $recording->switch_json['url']); self::assertSame('[REDACTED]', $recording->switch_json['custom_channel_vars']['Password']);
        $this->assertSoftDeleted($missingRecent); $this->assertNotSoftDeleted($older);
        $this->assertDatabaseHas('switch_sync_checkpoints', ['switch_account_id' => $account->getKey(), 'resource_type' => 'recordings', 'status' => 'healthy']);
    }
}
