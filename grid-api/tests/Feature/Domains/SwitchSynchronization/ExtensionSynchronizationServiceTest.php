<?php

namespace Tests\Feature\Domains\SwitchSynchronization;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Contracts\SwitchExtensionGateway;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use App\Domains\SwitchSynchronization\Services\ExtensionSynchronizationService;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailGreeting;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExtensionSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_projects_extension_details_and_soft_deletes_missing_records(): void
    {
        $account = SwitchAccount::factory()->create();
        $missing = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'removed-user',
        ]);
        $missingDevice = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'removed-device',
        ]);
        $missingVoicemailBox = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'removed-vmbox',
        ]);
        $missingCallflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'removed-callflow',
        ]);
        $missingVoicemailMessage = SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($missingVoicemailBox, 'voicemailBox')
            ->create(['switch_resource_id' => 'removed-message']);
        $missingGreeting = SwitchVoicemailGreeting::factory()
            ->for($account, 'switchAccount')
            ->for($missingVoicemailBox, 'voicemailBox')
            ->create(['switch_resource_id' => 'removed-media']);
        $run = SyncRun::query()->create([
            'switch_account_id' => $account->getKey(),
            'resource_type' => 'extensions',
            'status' => SyncRunStatus::Queued,
        ]);

        $this->app->instance(SwitchExtensionGateway::class, new class implements SwitchExtensionGateway
        {
            public function users(SwitchAccount $account): iterable
            {
                yield [
                    'id' => 'switch-user-1',
                    'username' => 'alice',
                    'first_name' => 'Alice',
                    'last_name' => 'Operator',
                    'email' => 'alice@example.test',
                    'caller_id' => ['internal' => ['number' => '1001']],
                    'timezone' => 'Asia/Manila',
                    'enabled' => true,
                    '_rev' => '2-test',
                    'password' => 'source-only-user-password',
                    'custom_profile' => ['department' => 'Operations'],
                ];
            }

            public function devices(SwitchAccount $account): iterable
            {
                yield [
                    'id' => 'switch-device-1',
                    'owner_id' => 'switch-user-1',
                    'name' => 'Alice Desk Phone',
                    'device_type' => 'sip_device',
                    'make' => 'Yealink',
                    'model' => 'T54W',
                    'mac_address' => '00:11:22:33:44:55',
                    'enabled' => true,
                    'sip' => ['password' => 'source-only-password'],
                ];
            }

            public function deviceStatuses(SwitchAccount $account): array
            {
                return ['switch-device-1' => true];
            }

            public function voicemailBoxes(SwitchAccount $account): iterable
            {
                yield [
                    'id' => 'switch-vmbox-1',
                    'owner_id' => 'switch-user-1',
                    'name' => 'Alice Voicemail',
                    'mailbox' => '1001',
                    'is_setup' => true,
                    'pin' => 'source-only-pin',
                    'media' => ['unavailable' => 'switch-media-1'],
                ];
            }

            public function voicemailMessages(SwitchAccount $account, string $voicemailBoxResourceId): iterable
            {
                yield [
                    'media_id' => 'switch-message-1',
                    'folder' => 'new',
                    'caller_id_name' => 'Customer',
                    'caller_id_number' => '+15551234567',
                    'from' => 'sip:+15551234567@example.test',
                    'to' => 'sip:1001@example.test',
                    'length' => 42000,
                    'timestamp' => 63892019200,
                    'transcription' => [
                        'result' => 'success',
                        'text' => 'Please call me back.',
                    ],
                    'pin' => 'message-source-secret',
                ];
            }

            public function media(SwitchAccount $account, string $mediaResourceId): array
            {
                return [
                    'id' => $mediaResourceId,
                    'name' => 'Alice unavailable greeting',
                    'description' => 'greeting.mp3',
                    'content_type' => 'audio/mpeg',
                    'content_length' => 4096,
                    'media_source' => 'upload',
                    'streamable' => true,
                    'api_key' => 'media-source-secret',
                ];
            }

            public function callflows(SwitchAccount $account): iterable
            {
                yield new CallflowSnapshot([
                    'id' => 'switch-callflow-1',
                    'name' => 'Alice Callflow',
                    'numbers' => ['1001'],
                    'patterns' => [],
                    'flow' => [
                        'module' => 'user',
                        'data' => ['id' => 'switch-user-1'],
                        'children' => [
                            '_' => [
                                'module' => 'voicemail',
                                'data' => ['id' => 'switch-vmbox-1'],
                                'children' => [],
                            ],
                        ],
                    ],
                ]);
            }
        });

        $this->app->make(ExtensionSynchronizationService::class)->handle($run);

        $projectedExtension = SwitchExtension::query()->where('switch_resource_id', 'switch-user-1')->firstOrFail();
        $projectedDevice = SwitchDevice::query()->where('switch_resource_id', 'switch-device-1')->firstOrFail();
        $projectedVoicemailBox = SwitchVoicemailBox::query()->where('switch_resource_id', 'switch-vmbox-1')->firstOrFail();
        $projectedCallflow = SwitchCallflow::query()->where('switch_resource_id', 'switch-callflow-1')->firstOrFail();
        $projectedVoicemailMessage = SwitchVoicemailMessage::query()->where('switch_resource_id', 'switch-message-1')->firstOrFail();
        $projectedGreeting = SwitchVoicemailGreeting::query()->where('switch_resource_id', 'switch-media-1')->firstOrFail();

        $this->assertDatabaseHas('switch_extensions', [
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => 'switch-user-1',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
            'sync_status' => 'healthy',
        ]);
        $this->assertSame('alice', $projectedExtension->switch_json['username']);
        $this->assertSame('[REDACTED]', $projectedExtension->switch_json['password']);
        $this->assertSame('Operations', $projectedExtension->switch_json['custom_profile']['department']);
        $this->assertSoftDeleted($missing);
        $this->assertDatabaseHas('switch_devices', [
            'switch_resource_id' => 'switch-device-1',
            'owner_switch_resource_id' => 'switch-user-1',
            'name' => 'Alice Desk Phone',
            'registration_status' => 'registered',
        ]);
        $this->assertNotNull($projectedDevice->registration_checked_at);
        $this->assertSame('[REDACTED]', $projectedDevice->switch_json['sip']['password']);
        $this->assertDatabaseHas('switch_voicemail_boxes', [
            'switch_resource_id' => 'switch-vmbox-1',
            'owner_switch_resource_id' => 'switch-user-1',
            'mailbox' => '1001',
        ]);
        $this->assertSame('[REDACTED]', $projectedVoicemailBox->switch_json['pin']);
        $this->assertDatabaseHas('switch_voicemail_messages', [
            'switch_account_id' => $account->getKey(),
            'switch_voicemail_box_id' => $projectedVoicemailBox->getKey(),
            'switch_resource_id' => 'switch-message-1',
            'folder' => 'new',
            'length' => 42000,
            'transcription_result' => 'success',
        ]);
        $this->assertSame('Please call me back.', $projectedVoicemailMessage->transcription_text);
        $this->assertSame('[REDACTED]', $projectedVoicemailMessage->switch_json['pin']);
        $this->assertDatabaseHas('switch_voicemail_greetings', [
            'switch_account_id' => $account->getKey(),
            'switch_voicemail_box_id' => $projectedVoicemailBox->getKey(),
            'switch_resource_id' => 'switch-media-1',
            'content_type' => 'audio/mpeg',
        ]);
        $this->assertSame('[REDACTED]', $projectedGreeting->switch_json['api_key']);
        $this->assertDatabaseHas('switch_callflows', [
            'switch_resource_id' => 'switch-callflow-1',
            'owner_switch_resource_id' => 'switch-user-1',
        ]);
        $this->assertSame(['user', 'voicemail'], $projectedCallflow->modules);
        $this->assertSame('user', $projectedCallflow->root_module);
        $this->assertSame(2, $projectedCallflow->node_count);
        $this->assertSame(2, $projectedCallflow->max_depth);
        $this->assertSame('resolved', $projectedCallflow->flow_structure['reference_status']);
        $this->assertSame($projectedExtension->id, $projectedCallflow->flow_structure['target']['id']);
        $this->assertSame($projectedVoicemailBox->id, $projectedCallflow->flow_structure['children']['_']['target']['id']);
        $this->assertSame('voicemail', $projectedCallflow->flow_structure['children']['_']['module']);
        $this->assertSame('switch-user-1', $projectedCallflow->switch_json['flow']['data']['id']);
        $this->assertSoftDeleted($missingDevice);
        $this->assertSoftDeleted($missingVoicemailBox);
        $this->assertSoftDeleted($missingCallflow);
        $this->assertSoftDeleted($missingVoicemailMessage);
        $this->assertSoftDeleted($missingGreeting);
        $this->assertDatabaseHas('switch_sync_runs', [
            'id' => $run->id,
            'status' => 'succeeded',
            'processed_count' => 6,
            'deleted_count' => 6,
        ]);
        $this->assertDatabaseHas('switch_sync_checkpoints', [
            'switch_account_id' => $account->getKey(),
            'status' => 'healthy',
        ]);
    }
}
