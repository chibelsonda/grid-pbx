<?php

namespace App\Domains\Queues\Services;

use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\Queues\Contracts\SwitchQueueGateway;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Illuminate\Support\Facades\DB;

class QueueSynchronizationService
{
    public function __construct(private readonly SwitchQueueGateway $gateway, private readonly QueueProjectionService $projection, private readonly CallflowReferenceResolver $callflowReferences) {}

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

            $missing = SwitchQueue::query()->where('switch_account_id', $account->getKey())
                ->when($snapshots !== [], fn ($query) => $query->whereNotIn('switch_resource_id', array_keys($snapshots)))->get();
            SwitchQueue::destroy($missing->modelKeys());
            $this->callflowReferences->refresh($account);
            $run->update([
                'status' => SyncRunStatus::Succeeded, 'processed_count' => count($snapshots),
                'upserted_count' => count($snapshots), 'deleted_count' => $missing->count(), 'finished_at' => now(),
            ]);
            SyncCheckpoint::query()->updateOrCreate(
                ['switch_account_id' => $account->getKey(), 'resource_type' => 'queues'],
                ['last_sync_run_id' => $run->getKey(), 'cursor' => null, 'status' => ProjectionStatus::Healthy, 'last_successful_at' => now(), 'error_message' => null],
            );
        });
    }
}
