<?php

namespace App\Domains\KazooSynchronization\Application\Jobs;

use App\Domains\KazooSynchronization\Application\Actions\SynchronizeExtensions;
use App\Domains\KazooSynchronization\Domain\ProjectionStatus;
use App\Domains\KazooSynchronization\Domain\SyncRunStatus;
use App\Domains\KazooSynchronization\Infrastructure\Models\SyncCheckpoint;
use App\Domains\KazooSynchronization\Infrastructure\Models\SyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncKazooExtensionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly string $syncRunId,
        public readonly string $kazooAccountId,
    ) {
        $this->onQueue('sync');
    }

    public function uniqueId(): string
    {
        return "extensions:{$this->kazooAccountId}";
    }

    public function handle(SynchronizeExtensions $synchronize): void
    {
        $run = SyncRun::query()->findOrFail($this->syncRunId);
        $synchronize->handle($run);
    }

    public function failed(?Throwable $exception): void
    {
        $run = SyncRun::query()->find($this->syncRunId);

        if ($run === null) {
            return;
        }

        $message = mb_substr($exception?->getMessage() ?? 'The Kazoo synchronization failed.', 0, 2000);
        $run->update([
            'status' => SyncRunStatus::Failed,
            'error_code' => $exception === null ? null : $exception::class,
            'error_message' => $message,
            'finished_at' => now(),
        ]);

        SyncCheckpoint::query()->updateOrCreate([
            'kazoo_account_id' => $this->kazooAccountId,
            'resource_type' => 'extensions',
        ], [
            'last_sync_run_id' => $run->getKey(),
            'status' => ProjectionStatus::Error,
            'error_message' => $message,
        ]);
    }
}
