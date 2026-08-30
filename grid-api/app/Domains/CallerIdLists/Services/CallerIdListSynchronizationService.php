<?php

namespace App\Domains\CallerIdLists\Services;

use App\Domains\CallerIdLists\Contracts\SwitchCallerIdListGateway;
use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Illuminate\Support\Facades\DB;

class CallerIdListSynchronizationService
{
    public function __construct(
        private readonly SwitchCallerIdListGateway $gateway,
        private readonly CallerIdListProjectionService $projection,
        private readonly CallflowReferenceResolver $callflowReferences,
    ) {}

    public function handle(SyncRun $run): void
    {
        $run->update([
            'status' => SyncRunStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);
        $account = $run->switchAccount()->firstOrFail();
        $snapshots = [];

        foreach ($this->gateway->all($account) as $snapshot) {
            $resourceId = $snapshot['list']['id'] ?? null;

            if (is_string($resourceId) && $resourceId !== '') {
                $snapshots[$resourceId] = $snapshot;
            }
        }

        DB::transaction(function () use ($account, $run, $snapshots): void {
            foreach ($snapshots as $snapshot) {
                $this->projection->project($account, $snapshot);
            }

            $missing = SwitchCallerIdList::query()
                ->where('switch_account_id', $account->getKey())
                ->when($snapshots !== [], fn ($query) => $query->whereNotIn('switch_resource_id', array_keys($snapshots)))
                ->get();
            SwitchCallerIdList::destroy($missing->modelKeys());
            $this->callflowReferences->refresh($account);
            $processed = count($snapshots);
            $run->update([
                'status' => SyncRunStatus::Succeeded,
                'processed_count' => $processed,
                'upserted_count' => $processed,
                'deleted_count' => $missing->count(),
                'finished_at' => now(),
            ]);
            SyncCheckpoint::query()->updateOrCreate(
                ['switch_account_id' => $account->getKey(), 'resource_type' => 'caller_id_lists'],
                [
                    'last_sync_run_id' => $run->getKey(),
                    'cursor' => null,
                    'status' => ProjectionStatus::Healthy,
                    'last_successful_at' => now(),
                    'error_message' => null,
                ],
            );
        });
    }
}
