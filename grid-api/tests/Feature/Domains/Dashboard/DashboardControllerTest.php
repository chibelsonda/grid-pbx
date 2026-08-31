<?php

namespace Tests\Feature\Domains\Dashboard;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Devices\Enums\DeviceRegistrationStatus;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_accessible_user_views_safe_projection_health_inventory_and_today_call_summary(): void
    {
        $this->travelTo('2026-08-31 12:00:00');
        [$user, $account] = $this->accessibleAccount([
            'timezone' => 'America/Los_Angeles',
            'last_synced_at' => now()->subMinutes(10),
            'sync_status' => ProjectionStatus::Healthy->value,
        ]);

        SwitchExtension::factory()->for($account)->count(2)->create();
        SwitchExtension::factory()->for($account)->create(['is_enabled' => false]);
        SwitchDevice::factory()->for($account)->create([
            'registration_status' => DeviceRegistrationStatus::Registered,
        ]);
        SwitchDevice::factory()->for($account)->create([
            'registration_status' => DeviceRegistrationStatus::Unregistered,
        ]);
        SwitchDevice::factory()->for($account)->create([
            'is_enabled' => false,
            'registration_status' => DeviceRegistrationStatus::Unregistered,
        ]);
        $healthyCallflow = SwitchCallflow::factory()->for($account)->create();
        SwitchCallflow::factory()->for($account)->create([
            'sync_status' => ProjectionStatus::Stale,
        ]);
        SwitchPhoneNumber::factory()->for($account)->create([
            'assigned_callflow_id' => $healthyCallflow->getKey(),
        ]);
        SwitchPhoneNumber::factory()->for($account)->create();
        $mailbox = SwitchVoicemailBox::factory()->for($account)->create();
        SwitchVoicemailMessage::factory()->for($account)->for($mailbox, 'voicemailBox')->create([
            'folder' => 'new',
        ]);
        SwitchQueue::factory()->for($account)->create();
        $this->checkpoint($account, 'extensions', ProjectionStatus::Healthy, now()->subMinutes(5));
        $this->checkpoint($account, 'devices', ProjectionStatus::Error, now()->subHour());
        $this->checkpoint($account, 'callflows', ProjectionStatus::Stale, now()->subHours(2));
        SyncRun::query()->create([
            'switch_account_id' => $account->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'resource_type' => 'devices',
            'status' => SyncRunStatus::Failed,
            'error_code' => 'PrivateException',
            'error_message' => 'SQLSTATE password=do-not-expose',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        SwitchCallDetailRecord::factory()->for($account)->create([
            'direction' => 'inbound',
            'started_at' => '2026-08-31 08:00:00',
            'billing_seconds' => 60,
        ]);
        SwitchCallDetailRecord::factory()->for($account)->create([
            'direction' => 'outbound',
            'started_at' => '2026-08-31 09:00:00',
            'billing_seconds' => 0,
        ]);
        SwitchCallDetailRecord::factory()->for($account)->create([
            'direction' => 'inbound',
            'started_at' => '2026-08-30 06:59:59',
            'billing_seconds' => 30,
        ]);
        SwitchCallDetailRecord::factory()->for($account)->create([
            'direction' => 'internal',
            'started_at' => '2026-08-31 10:00:00',
            'billing_seconds' => 30,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/dashboard");

        $response->assertOk()
            ->assertJsonPath('data.account.id', $account->id)
            ->assertJsonPath('data.account.timezone', 'America/Los_Angeles')
            ->assertJsonPath('data.synchronization.status', 'error')
            ->assertJsonPath('data.synchronization.checkpoints.healthy', 1)
            ->assertJsonPath('data.synchronization.checkpoints.stale', 1)
            ->assertJsonPath('data.synchronization.checkpoints.error', 1)
            ->assertJsonPath('data.synchronization.recent_runs.0.status', 'failed')
            ->assertJsonPath('data.inventory.extensions.total', 3)
            ->assertJsonPath('data.inventory.extensions.disabled', 1)
            ->assertJsonPath('data.inventory.devices.registered', 1)
            ->assertJsonPath('data.inventory.devices.unregistered', 2)
            ->assertJsonPath('data.inventory.devices.enabled_unregistered', 1)
            ->assertJsonPath('data.inventory.devices.unknown_registration', 0)
            ->assertJsonPath('data.inventory.phone_numbers.assigned', 1)
            ->assertJsonPath('data.inventory.phone_numbers.unassigned', 1)
            ->assertJsonPath('data.inventory.callflows.attention', 1)
            ->assertJsonPath('data.inventory.voicemail.new_messages', 1)
            ->assertJsonPath('data.inventory.queues.total', 1)
            ->assertJsonPath('data.calls_today.total', 2)
            ->assertJsonPath('data.calls_today.inbound', 1)
            ->assertJsonPath('data.calls_today.outbound', 1)
            ->assertJsonPath('data.calls_today.answered', 1)
            ->assertJsonPath('data.calls_today.missed', 1)
            ->assertJsonPath('data.calls_today.answer_rate', 50)
            ->assertJsonPath('data.calls_today.average_duration_seconds', 60)
            ->assertJsonPath('data.attention.total', 5)
            ->assertJsonPath('data.attention.items.0.code', 'failed_synchronizations')
            ->assertJsonPath('data.attention.items.2.code', 'unregistered_devices')
            ->assertJsonPath('data.attention.items.2.count', 1)
            ->assertJsonStructure(['data' => ['generated_at', 'data_as_of', 'is_stale']])
            ->assertJsonMissing(['SQLSTATE'])
            ->assertJsonMissing(['do-not-expose'])
            ->assertJsonMissing(['PrivateException'])
            ->assertJsonMissingPath('data.account.switch_account_id')
            ->assertDontSee($account->switch_account_id);
    }

    public function test_dashboard_reports_not_started_without_checkpoints(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $response = $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/dashboard");

        $response->assertOk()
            ->assertJsonPath('data.synchronization.status', 'not_started')
            ->assertJsonPath('data.is_stale', true)
            ->assertJsonPath('data.attention.total', 1)
            ->assertJsonPath('data.attention.items.0.code', 'synchronization_not_started');
    }

    public function test_returns_401_for_an_unauthenticated_dashboard_request(): void
    {
        $account = SwitchAccount::factory()->create();

        $this->getJson("/api/v1/accounts/{$account->id}/dashboard")->assertUnauthorized();
    }

    public function test_returns_404_for_another_organizations_dashboard(): void
    {
        [, $account] = $this->accessibleAccount();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard")
            ->assertNotFound();
    }

    private function checkpoint(
        SwitchAccount $account,
        string $resource,
        ProjectionStatus $status,
        ?\DateTimeInterface $lastSuccessfulAt,
    ): SyncCheckpoint {
        return SyncCheckpoint::query()->create([
            'switch_account_id' => $account->getKey(),
            'resource_type' => $resource,
            'status' => $status,
            'last_successful_at' => $lastSuccessfulAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $accountAttributes
     * @return array{User, SwitchAccount}
     */
    private function accessibleAccount(array $accountAttributes = []): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, [
            'role' => OrganizationRole::ReadOnlyUser->value,
        ]);

        return [
            $user,
            SwitchAccount::factory()->for($organization)->create($accountAttributes),
        ];
    }
}
