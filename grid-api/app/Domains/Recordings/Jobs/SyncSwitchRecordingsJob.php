<?php

namespace App\Domains\Recordings\Jobs;

use App\Domains\Recordings\Services\RecordingSynchronizationService;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncSwitchRecordingsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3; public int $timeout = 110; public int $uniqueFor = 300; public array $backoff = [10, 30, 60];
    public function __construct(public readonly string $syncRunId, public readonly string $switchAccountId) { $this->onQueue('sync'); }
    public function uniqueId(): string { return "recordings:{$this->switchAccountId}"; }
    public function handle(RecordingSynchronizationService $service): void { $service->handle(SyncRun::query()->findOrFail($this->syncRunId)); }
    public function failed(?Throwable $exception): void { $run = SyncRun::query()->find($this->syncRunId); if ($run === null) return; $message = mb_substr($exception?->getMessage() ?? 'The Switch recording synchronization failed.', 0, 2000); $run->update(['status' => SyncRunStatus::Failed, 'error_code' => $exception === null ? null : $exception::class, 'error_message' => $message, 'finished_at' => now()]); SyncCheckpoint::query()->updateOrCreate(['switch_account_id' => $this->switchAccountId, 'resource_type' => 'recordings'], ['last_sync_run_id' => $run->getKey(), 'status' => ProjectionStatus::Error, 'error_message' => $message]); }
}
