<?php

namespace Tests\Feature\Domains\Media;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Contracts\SwitchMediaGateway;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Media\Services\MediaSynchronizationService;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MediaSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_projects_media_sets_music_on_hold_and_soft_deletes_missing_records(): void
    {
        $account = SwitchAccount::factory()->create();
        $user = User::factory()->create();
        $missing = SwitchMedia::factory()->for($account)->create([
            'switch_resource_id' => 'switch-media-missing',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'modules' => ['play'],
            'flow_structure' => [
                'module' => 'play',
                'target' => null,
                'reference_status' => 'unresolved',
                'children' => [],
            ],
            'switch_json' => [
                'flow' => [
                    'module' => 'play',
                    'data' => ['id' => 'switch-media-hold'],
                    'children' => [],
                ],
            ],
        ]);
        $run = $account->syncRuns()->create([
            'requested_by_user_id' => $user->getKey(),
            'resource_type' => 'media',
            'status' => SyncRunStatus::Queued,
        ]);
        $gateway = $this->mock(SwitchMediaGateway::class);
        $gateway->shouldReceive('all')->once()->andReturn((function (): \Generator {
            yield [
                'id' => 'switch-media-hold',
                'name' => 'Main hold music',
                'description' => 'Default loop',
                'language' => 'en-us',
                'media_source' => 'upload',
                'content_type' => 'audio/mpeg',
                'content_length' => 4096,
                'streamable' => true,
                'sip' => ['password' => 'do-not-store'],
            ];
            yield [
                'id' => 'switch-media-prompt',
                'name' => 'Welcome prompt',
                'media_source' => 'tts',
                'streamable' => true,
            ];
        })());
        $gateway->shouldReceive('accountMusicOnHold')->once()->andReturn('switch-media-hold');

        $this->app->make(MediaSynchronizationService::class)->handle($run);

        $hold = SwitchMedia::query()->where('switch_resource_id', 'switch-media-hold')->firstOrFail();
        $this->assertSame('Main hold music', $hold->name);
        $this->assertSame('[REDACTED]', $hold->switch_json['sip']['password']);
        $this->assertSame($hold->getKey(), $account->fresh()->music_on_hold_media_id);
        $this->assertSame($hold->id, $callflow->fresh()->flow_structure['target']['id']);
        $this->assertSame('resolved', $callflow->fresh()->flow_structure['reference_status']);
        $this->assertSoftDeleted($missing);
        $this->assertDatabaseHas('switch_sync_runs', [
            'sync_run_id' => $run->getKey(),
            'status' => SyncRunStatus::Succeeded->value,
            'processed_count' => 2,
            'deleted_count' => 1,
        ]);
        $this->assertDatabaseHas('switch_sync_checkpoints', [
            'switch_account_id' => $account->getKey(),
            'resource_type' => 'media',
            'status' => 'healthy',
        ]);
    }
}
