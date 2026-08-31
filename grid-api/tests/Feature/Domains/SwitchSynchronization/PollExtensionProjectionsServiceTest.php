<?php

namespace Tests\Feature\Domains\SwitchSynchronization;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Jobs\SyncSwitchExtensionsJob;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Services\PollExtensionProjectionsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PollExtensionProjectionsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_does_nothing_while_polling_is_disabled(): void
    {
        Queue::fake();
        SwitchAccount::factory()->create(['is_enabled' => true]);

        $result = app(PollExtensionProjectionsService::class)->handle();

        $this->assertSame(['enabled' => false, 'scheduled' => 0], $result);
        $this->assertDatabaseCount('switch_sync_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_it_schedules_only_due_enabled_accounts_within_the_batch_cap(): void
    {
        Queue::fake();
        config()->set('switch.extension_polling', [
            'enabled' => true,
            'interval_minutes' => 15,
            'batch_size' => 2,
        ]);
        $neverSynced = SwitchAccount::factory()->create(['is_enabled' => true]);
        $stale = SwitchAccount::factory()->create(['is_enabled' => true]);
        $thirdDue = SwitchAccount::factory()->create(['is_enabled' => true]);
        $fresh = SwitchAccount::factory()->create(['is_enabled' => true]);
        $syncing = SwitchAccount::factory()->create(['is_enabled' => true]);
        $disabled = SwitchAccount::factory()->create(['is_enabled' => false]);

        $this->checkpoint($stale, ProjectionStatus::Healthy, now()->subMinutes(20));
        $this->checkpoint($fresh, ProjectionStatus::Healthy, now()->subMinutes(5));
        $this->checkpoint($syncing, ProjectionStatus::Syncing, now()->subHour());

        $result = app(PollExtensionProjectionsService::class)->handle();

        $this->assertSame(['enabled' => true, 'scheduled' => 2], $result);
        $this->assertDatabaseCount('switch_sync_runs', 2);
        $this->assertDatabaseHas('switch_sync_runs', [
            'switch_account_id' => $neverSynced->getKey(),
            'requested_by_user_id' => null,
            'resource_type' => 'extensions',
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('switch_sync_runs', [
            'switch_account_id' => $stale->getKey(),
            'requested_by_user_id' => null,
            'resource_type' => 'extensions',
            'status' => 'queued',
        ]);
        $this->assertDatabaseMissing('switch_sync_runs', ['switch_account_id' => $thirdDue->getKey()]);
        $this->assertDatabaseMissing('switch_sync_runs', ['switch_account_id' => $fresh->getKey()]);
        $this->assertDatabaseMissing('switch_sync_runs', ['switch_account_id' => $syncing->getKey()]);
        $this->assertDatabaseMissing('switch_sync_runs', ['switch_account_id' => $disabled->getKey()]);
        Queue::assertPushed(SyncSwitchExtensionsJob::class, 2);

        $this->assertSame(
            ['enabled' => true, 'scheduled' => 1],
            app(PollExtensionProjectionsService::class)->handle(),
        );
        $this->assertDatabaseHas('switch_sync_runs', [
            'switch_account_id' => $thirdDue->getKey(),
            'requested_by_user_id' => null,
        ]);
        $this->assertSame(
            ['enabled' => true, 'scheduled' => 0],
            app(PollExtensionProjectionsService::class)->handle(),
        );
        $this->assertDatabaseCount('switch_sync_runs', 3);
        Queue::assertPushed(SyncSwitchExtensionsJob::class, 3);
    }

    private function checkpoint(
        SwitchAccount $account,
        ProjectionStatus $status,
        \DateTimeInterface $lastSuccessfulAt,
    ): void {
        $checkpoint = SyncCheckpoint::query()->create([
            'switch_account_id' => $account->getKey(),
            'resource_type' => 'extensions',
            'status' => $status,
            'last_successful_at' => $lastSuccessfulAt,
        ]);
        $checkpoint->timestamps = false;
        $checkpoint->updated_at = $lastSuccessfulAt;
        $checkpoint->save();
    }
}
