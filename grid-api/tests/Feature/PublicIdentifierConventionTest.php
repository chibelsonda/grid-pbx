<?php

namespace Tests\Feature;

use App\Domains\Auditing\Models\AuditLog;
use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\ExtensionLifecycleOperation;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailGreeting;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicIdentifierConventionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_seeder_generates_public_uuids(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Str::isUuid(User::query()->firstOrFail()->id));
        $this->assertTrue(Str::isUuid(Organization::query()->firstOrFail()->id));
    }

    public function test_application_entities_use_public_uuids_and_named_hidden_primary_keys(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $account = SwitchAccount::factory()->for($organization)->create();
        $extension = SwitchExtension::factory()->for($account)->create();
        $extensionOperation = ExtensionLifecycleOperation::query()->create([
            'switch_account_id' => $account->getKey(),
            'switch_extension_id' => $extension->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'operation' => 'delete',
            'status' => 'running',
            'completed_steps' => [],
        ]);
        $device = SwitchDevice::factory()->for($account)->create();
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        $voicemailMessage = SwitchVoicemailMessage::factory()
            ->for($account)
            ->for($voicemailBox, 'voicemailBox')
            ->create();
        $voicemailGreeting = SwitchVoicemailGreeting::factory()
            ->for($account)
            ->for($voicemailBox, 'voicemailBox')
            ->create();
        $callflow = SwitchCallflow::factory()->for($account)->create();
        $phoneNumber = SwitchPhoneNumber::factory()->for($account)->create();
        $callDetailRecord = SwitchCallDetailRecord::factory()->for($account)->create();
        $syncRun = $account->syncRuns()->create([
            'requested_by_user_id' => $user->getKey(),
            'resource_type' => 'extensions',
            'status' => SyncRunStatus::Queued,
        ]);
        $syncCheckpoint = SyncCheckpoint::query()->create([
            'switch_account_id' => $account->getKey(),
            'last_sync_run_id' => $syncRun->getKey(),
            'resource_type' => 'extensions',
            'status' => ProjectionStatus::Syncing,
        ]);
        $auditLog = AuditLog::query()->create([
            'user_id' => $user->getKey(),
            'organization_id' => $organization->getKey(),
            'switch_account_id' => $account->getKey(),
            'request_id' => (string) Str::uuid(),
            'action' => 'identifier.tested',
            'resource_type' => 'test',
            'outcome' => 'succeeded',
            'created_at' => now(),
        ]);

        $this->assertIdentifier($user, 'user_id');
        $this->assertIdentifier($organization, 'organization_id');
        $this->assertIdentifier($account, 'account_id');
        $this->assertIdentifier($extension, 'extension_id');
        $this->assertIdentifier($extensionOperation, 'extension_lifecycle_operation_id');
        $this->assertIdentifier($device, 'device_id');
        $this->assertIdentifier($voicemailBox, 'voicemail_box_id');
        $this->assertIdentifier($voicemailMessage, 'voicemail_message_id');
        $this->assertIdentifier($voicemailGreeting, 'voicemail_greeting_id');
        $this->assertIdentifier($callflow, 'callflow_id');
        $this->assertIdentifier($phoneNumber, 'phone_number_id');
        $this->assertIdentifier($callDetailRecord, 'call_detail_record_id');
        $this->assertIdentifier($syncRun, 'sync_run_id');
        $this->assertIdentifier($syncCheckpoint, 'sync_checkpoint_id');
        $this->assertIdentifier($auditLog, 'audit_log_id');
    }

    private function assertIdentifier(Model $model, string $primaryKey): void
    {
        $this->assertSame($primaryKey, $model->getKeyName());
        $this->assertTrue(Str::isUuid($model->id));
        $this->assertNotSame((string) $model->getKey(), $model->id);
        $this->assertArrayNotHasKey($primaryKey, $model->toArray());
    }
}
