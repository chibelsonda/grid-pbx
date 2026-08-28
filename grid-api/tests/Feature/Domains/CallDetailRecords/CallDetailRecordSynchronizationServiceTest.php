<?php

namespace Tests\Feature\Domains\CallDetailRecords;

use App\Domains\CallDetailRecords\Contracts\SwitchCallDetailRecordGateway;
use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\CallDetailRecords\Services\CallDetailRecordSynchronizationService;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use DateTimeInterface;
use Generator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use UnexpectedValueException;

class CallDetailRecordSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_projects_a_bounded_safe_cdr_summary_idempotently_and_links_the_owner(): void
    {
        $this->travelTo('2026-08-28 12:00:00');
        config(['switch.cdr_import_window_days' => 7]);
        $account = SwitchAccount::factory()->create();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1',
        ]);
        $run = $account->syncRuns()->create([
            'requested_by_user_id' => User::factory()->create()->getKey(),
            'resource_type' => 'call_detail_records',
            'status' => SyncRunStatus::Queued,
        ]);
        $gateway = new class implements SwitchCallDetailRecordGateway
        {
            public ?DateTimeInterface $from = null;

            public ?DateTimeInterface $to = null;

            public function all(
                SwitchAccount $account,
                DateTimeInterface $from,
                DateTimeInterface $to,
            ): Generator {
                $this->from = $from;
                $this->to = $to;

                yield [
                    'switch_resource_id' => '202608-cdr-1',
                    'call_id' => 'call-1',
                    'interaction_id' => 'interaction-1',
                    'direction' => 'inbound',
                    'caller_id_name' => 'Alice Caller',
                    'caller_id_number' => '+14155550100',
                    'callee_id_name' => 'Grid Support',
                    'callee_id_number' => '1001',
                    'from_uri' => 'alice@example.test',
                    'to_uri' => '1001@gridpbx.test',
                    'request_uri' => '1001@gridpbx.test',
                    'started_at_unix' => 1787918400,
                    'duration_seconds' => 75,
                    'billing_seconds' => 60,
                    'hangup_cause' => 'NORMAL_CLEARING',
                    'disposition' => 'SUCCESS',
                    'owner_switch_resource_id' => 'switch-user-1',
                    'recording_available' => true,
                    'data' => [
                        'id' => '202608-cdr-1',
                        'call_id' => 'call-1',
                        'direction' => 'inbound',
                        'caller_id_name' => 'Alice Caller',
                        'duration_seconds' => 75,
                        'cost' => '9.99',
                        'rate' => '0.50',
                        'recording_url' => 'https://switch.test/private-recording',
                        'media_recordings' => [['id' => 'private-media-id']],
                        'authorizing_id' => 'private-device-id',
                        'auth_token' => 'must-not-be-stored',
                    ],
                ];
            }
        };
        $this->app->instance(SwitchCallDetailRecordGateway::class, $gateway);

        $this->app->make(CallDetailRecordSynchronizationService::class)->handle($run);

        $record = SwitchCallDetailRecord::query()->sole();
        $this->assertSame($extension->getKey(), $record->switch_extension_id);
        $this->assertSame('call-1', $record->call_id);
        $this->assertSame('inbound', $record->direction);
        $this->assertSame(75, $record->duration_seconds);
        $this->assertTrue($record->recording_available);
        $this->assertSame('2026-08-21', $gateway->from?->format('Y-m-d'));
        $this->assertSame('2026-08-28', $gateway->to?->format('Y-m-d'));
        $this->assertSame('202608-cdr-1', $record->switch_json['id']);
        $this->assertSame('Alice Caller', $record->switch_json['caller_id_name']);
        $this->assertArrayNotHasKey('cost', $record->switch_json);
        $this->assertArrayNotHasKey('rate', $record->switch_json);
        $this->assertArrayNotHasKey('recording_url', $record->switch_json);
        $this->assertArrayNotHasKey('media_recordings', $record->switch_json);
        $this->assertArrayNotHasKey('authorizing_id', $record->switch_json);
        $this->assertArrayNotHasKey('auth_token', $record->switch_json);
        $this->assertDatabaseHas('switch_sync_runs', [
            'sync_run_id' => $run->getKey(),
            'status' => 'succeeded',
            'processed_count' => 1,
            'deleted_count' => 0,
        ]);
        $this->assertDatabaseHas('switch_sync_checkpoints', [
            'switch_account_id' => $account->getKey(),
            'resource_type' => 'call_detail_records',
            'status' => 'healthy',
        ]);
    }

    public function test_rejects_an_import_window_outside_the_supported_crossbar_range(): void
    {
        config(['switch.cdr_import_window_days' => 32]);
        $account = SwitchAccount::factory()->create();
        $run = $account->syncRuns()->create([
            'requested_by_user_id' => User::factory()->create()->getKey(),
            'resource_type' => 'call_detail_records',
            'status' => SyncRunStatus::Queued,
        ]);
        $this->mock(SwitchCallDetailRecordGateway::class)->shouldNotReceive('all');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('between 1 and 31 days');

        $this->app->make(CallDetailRecordSynchronizationService::class)->handle($run);
    }
}
