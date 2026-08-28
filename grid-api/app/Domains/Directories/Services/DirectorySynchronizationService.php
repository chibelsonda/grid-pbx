<?php

namespace App\Domains\Directories\Services;

use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\Directories\Contracts\SwitchDirectoryGateway;
use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Illuminate\Support\Facades\DB;

class DirectorySynchronizationService
{
    public function __construct(
        private readonly SwitchDirectoryGateway $gateway,
        private readonly DirectoryProjectionService $projection,
        private readonly CallflowReferenceResolver $callflowReferences,
    ) {}

    public function handle(SyncRun $run): void
    {
        $run->update(['status' => SyncRunStatus::Running, 'started_at' => now(), 'finished_at' => null, 'error_code' => null, 'error_message' => null]);
        $account = $run->switchAccount()->firstOrFail();
        $snapshots = [];

        foreach ($this->gateway->all($account) as $snapshot) {
            if (is_string($snapshot['id'] ?? null) && $snapshot['id'] !== '') {
                $snapshots[$snapshot['id']] = $snapshot;
            }
        }

        DB::transaction(function () use ($account, $run, $snapshots): void {
            foreach ($snapshots as $snapshot) {
                $this->projection->project($account, $snapshot);
            }
            $missing = SwitchDirectory::query()->where('switch_account_id', $account->getKey())
                ->when($snapshots !== [], fn ($query) => $query->whereNotIn('switch_resource_id', array_keys($snapshots)))->get();
            SwitchDirectory::destroy($missing->modelKeys());
            $this->callflowReferences->refresh($account);
            $run->update(['status' => SyncRunStatus::Succeeded, 'processed_count' => count($snapshots), 'upserted_count' => count($snapshots), 'deleted_count' => $missing->count(), 'finished_at' => now()]);
            SyncCheckpoint::query()->updateOrCreate(['switch_account_id' => $account->getKey(), 'resource_type' => 'directories'], [
                'last_sync_run_id' => $run->getKey(), 'cursor' => null, 'status' => ProjectionStatus::Healthy,
                'last_successful_at' => now(), 'error_message' => null,
            ]);
        });
    }
}
