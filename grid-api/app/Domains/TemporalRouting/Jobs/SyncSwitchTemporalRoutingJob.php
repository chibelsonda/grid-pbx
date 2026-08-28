<?php

namespace App\Domains\TemporalRouting\Jobs;

use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use App\Domains\TemporalRouting\Services\TemporalRoutingSynchronizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncSwitchTemporalRoutingJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 110;

    public int $uniqueFor = 300;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly string $syncRunId, public readonly string $switchAccountId)
    {
        $this->onQueue('sync');
    }

    public function uniqueId(): string
    {
        return "temporal_routing:{$this->switchAccountId}";
    }

    public function handle(TemporalRoutingSynchronizationService $service): void
    {
        $service->handle(SyncRun::query()->findOrFail($this->syncRunId));
    }

    public function failed(?Throwable $e): void
    {
        $run = SyncRun::query()->find($this->syncRunId);
        if (! $run) {
            return;
        } $message = mb_substr($e?->getMessage() ?? 'The Switch temporal routing synchronization failed.', 0, 2000);
        $run->update(['status' => SyncRunStatus::Failed, 'error_code' => $e === null ? null : $e::class, 'error_message' => $message, 'finished_at' => now()]);
        SyncCheckpoint::query()->updateOrCreate(['switch_account_id' => $this->switchAccountId, 'resource_type' => 'temporal_routing'], ['last_sync_run_id' => $run->getKey(), 'status' => ProjectionStatus::Error, 'error_message' => $message]);
    }
}
