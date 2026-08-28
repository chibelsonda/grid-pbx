<?php

namespace Tests\Feature\Domains\SwitchSynchronization;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchCallflow;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Extensions\Models\SwitchVoicemailBox;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Contracts\SwitchExtensionGateway;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use App\Domains\SwitchSynchronization\Services\ExtensionSynchronizationService;
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

            public function voicemailBoxes(SwitchAccount $account): iterable
            {
                yield [
                    'id' => 'switch-vmbox-1',
                    'owner_id' => 'switch-user-1',
                    'name' => 'Alice Voicemail',
                    'mailbox' => '1001',
                    'is_setup' => true,
                    'pin' => 'source-only-pin',
                ];
            }

            public function callflows(SwitchAccount $account): iterable
            {
                yield [
                    'id' => 'switch-callflow-1',
                    'name' => 'Alice Callflow',
                    'numbers' => ['1001'],
                    'modules' => ['user', 'voicemail'],
                ];
            }
        });

        $this->app->make(ExtensionSynchronizationService::class)->handle($run);

        $projectedExtension = SwitchExtension::query()->where('switch_resource_id', 'switch-user-1')->firstOrFail();
        $projectedDevice = SwitchDevice::query()->where('switch_resource_id', 'switch-device-1')->firstOrFail();
        $projectedVoicemailBox = SwitchVoicemailBox::query()->where('switch_resource_id', 'switch-vmbox-1')->firstOrFail();
        $projectedCallflow = SwitchCallflow::query()->where('switch_resource_id', 'switch-callflow-1')->firstOrFail();

        $this->assertDatabaseHas('switch_extensions', [
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => 'switch-user-1',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
            'sync_status' => 'healthy',
        ]);
        $this->assertSame('alice', $projectedExtension->source_payload['username']);
        $this->assertSame('[REDACTED]', $projectedExtension->source_payload['password']);
        $this->assertSame('Operations', $projectedExtension->source_payload['custom_profile']['department']);
        $this->assertSoftDeleted($missing);
        $this->assertDatabaseHas('switch_devices', [
            'switch_resource_id' => 'switch-device-1',
            'owner_switch_resource_id' => 'switch-user-1',
            'name' => 'Alice Desk Phone',
        ]);
        $this->assertSame('[REDACTED]', $projectedDevice->source_payload['sip']['password']);
        $this->assertDatabaseHas('switch_voicemail_boxes', [
            'switch_resource_id' => 'switch-vmbox-1',
            'owner_switch_resource_id' => 'switch-user-1',
            'mailbox' => '1001',
        ]);
        $this->assertSame('[REDACTED]', $projectedVoicemailBox->source_payload['pin']);
        $this->assertDatabaseHas('switch_callflows', [
            'switch_resource_id' => 'switch-callflow-1',
            'owner_switch_resource_id' => 'switch-user-1',
        ]);
        $this->assertSame(['user', 'voicemail'], $projectedCallflow->source_payload['modules']);
        $this->assertSoftDeleted($missingDevice);
        $this->assertSoftDeleted($missingVoicemailBox);
        $this->assertSoftDeleted($missingCallflow);
        $this->assertDatabaseHas('switch_sync_runs', [
            'id' => $run->getKey(),
            'status' => 'succeeded',
            'processed_count' => 4,
            'deleted_count' => 4,
        ]);
        $this->assertDatabaseHas('switch_sync_checkpoints', [
            'switch_account_id' => $account->getKey(),
            'status' => 'healthy',
        ]);
    }
}
