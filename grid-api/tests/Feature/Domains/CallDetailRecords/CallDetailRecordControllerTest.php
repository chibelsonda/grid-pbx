<?php

namespace Tests\Feature\Domains\CallDetailRecords;

use App\Domains\CallDetailRecords\Jobs\SyncSwitchCallDetailRecordsJob;
use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Recordings\Models\SwitchRecording;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CallDetailRecordControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_filters_and_shows_safe_cdr_details_using_public_ids(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Support Operator',
            'extension' => '1001',
        ]);
        $matching = SwitchCallDetailRecord::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'caller_id_name' => 'Alice Caller',
            'caller_id_number' => '+14155550100',
            'callee_id_number' => '1001',
            'direction' => 'inbound',
            'started_at' => '2026-08-28 04:00:00',
            'duration_seconds' => 120,
            'billing_seconds' => 90,
            'hangup_cause' => 'NORMAL_CLEARING',
            'switch_json' => ['id' => 'private-upstream-cdr-id'],
        ]);
        SwitchCallDetailRecord::factory()->for($account)->create([
            'caller_id_name' => 'Other Caller',
            'direction' => 'outbound',
            'started_at' => '2026-08-20 04:00:00',
            'duration_seconds' => 20,
            'billing_seconds' => 0,
            'hangup_cause' => 'NO_ANSWER',
        ]);
        $recording = SwitchRecording::factory()->for($account)->create([
            'switch_call_detail_record_id' => $matching->getKey(),
            'name' => 'Support call recording',
            'duration_seconds' => 90,
            'has_audio' => true,
        ]);
        SwitchRecording::factory()->create([
            'switch_call_detail_record_id' => $matching->getKey(),
            'name' => 'Foreign recording',
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/call-detail-records"
            .'?search=Alice&direction=inbound&outcome=answered'
            .'&hangup_cause=NORMAL_CLEARING&started_from=2026-08-27&started_to=2026-08-29'
            .'&duration_min=60&duration_max=180',
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.caller.name', 'Alice Caller')
            ->assertJsonPath('data.0.extension.id', $extension->id)
            ->assertJsonPath('data.0.answered', true)
            ->assertJsonPath('data.0.recording_available', true)
            ->assertJsonCount(1, 'data.0.recordings')
            ->assertJsonPath('data.0.recordings.0.id', $recording->id)
            ->assertJsonPath('data.0.recordings.0.name', 'Support call recording')
            ->assertJsonPath('meta.import_window_days', 7)
            ->assertJsonMissingPath('data.0.call_detail_record_id')
            ->assertJsonMissingPath('data.0.switch_resource_id')
            ->assertJsonMissingPath('data.0.switch_account_id')
            ->assertJsonMissingPath('data.0.switch_json');
        $this->assertStringNotContainsString('private-upstream-cdr-id', $response->getContent());

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/call-detail-records/{$matching->id}")
            ->assertOk()
            ->assertJsonPath('data.call_id', $matching->call_id)
            ->assertJsonPath('data.duration_seconds', 120)
            ->assertJsonPath('data.recordings.0.id', $recording->id)
            ->assertJsonMissingPath('data.recordings.0.recording_id')
            ->assertJsonMissingPath('data.recordings.0.switch_resource_id')
            ->assertJsonMissingPath('data.switch_json');
    }

    public function test_returns_404_for_a_cdr_outside_the_accessible_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $otherRecord = SwitchCallDetailRecord::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/call-detail-records/{$otherRecord->id}")
            ->assertNotFound();
    }

    public function test_returns_422_when_filter_ranges_are_reversed(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson(
                "/api/v1/accounts/{$account->id}/call-detail-records"
                .'?started_from=2026-08-29&started_to=2026-08-28&duration_min=120&duration_max=60',
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.started_to.0',
                'The end date must be on or after the start date.',
            )
            ->assertJsonPath(
                'errors.duration_max.0',
                'The maximum duration must be greater than or equal to the minimum duration.',
            );
    }

    public function test_read_only_user_can_view_but_cannot_start_a_cdr_sync(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        SwitchCallDetailRecord::factory()->for($account)->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/call-detail-records")
            ->assertOk();
        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/call-detail-records")
            ->assertForbidden();
    }

    public function test_queues_a_cdr_sync_and_reuses_an_active_run(): void
    {
        Queue::fake([SyncSwitchCallDetailRecordsJob::class]);
        [$user, $account] = $this->accessibleAccount();

        $first = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/call-detail-records")
            ->assertAccepted()
            ->json('data.id');
        $second = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/call-detail-records")
            ->assertAccepted()
            ->json('data.id');

        $this->assertSame($first, $second);
        Queue::assertPushed(SyncSwitchCallDetailRecordsJob::class, 1);
        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/sync/call-detail-records/{$first}")
            ->assertOk()
            ->assertJsonPath('data.resource_type', 'call_detail_records')
            ->assertJsonPath('data.status', 'queued');
    }

    public function test_returns_401_for_an_unauthenticated_cdr_request(): void
    {
        $account = SwitchAccount::factory()->create();

        $this->getJson("/api/v1/accounts/{$account->id}/call-detail-records")
            ->assertUnauthorized();
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(
        OrganizationRole $role = OrganizationRole::AccountOperator,
    ): array {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->getKey(), ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
