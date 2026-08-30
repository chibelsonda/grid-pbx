<?php

namespace App\Domains\Services\Services;

use App\Domains\Services\Contracts\SwitchServiceGateway;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Illuminate\Support\Facades\DB;

class ServiceSynchronizationService
{
    public function __construct(private readonly SwitchServiceGateway $gateway, private readonly ServiceProjectionService $projection) {}

    public function handle(SyncRun $run): void
    {
        $run->update(['status' => SyncRunStatus::Running, 'started_at' => now(), 'finished_at' => null, 'error_code' => null, 'error_message' => null]);
        $account = $run->switchAccount()->firstOrFail();
        $snapshot = $this->gateway->snapshot($account);
        $incomingPlans = collect($snapshot['plans'] ?? [])->pluck('switch_resource_id')->filter()->all();
        $incomingQuantities = collect($snapshot['quantities'] ?? [])->map(fn ($row) => is_array($row) ? implode("\0", [$row['scope'] ?? '', $row['category'] ?? '', $row['item'] ?? '']) : '')->filter()->all();
        $existingPlans = $account->servicePlans()->pluck('switch_resource_id')->all();
        $existingQuantities = $account->serviceQuantities()
            ->get(['scope', 'category', 'item'])
            ->map(fn ($row) => implode("\0", [$row->scope, $row->category, $row->item]))
            ->all();
        $deletedCount = count(array_diff($existingPlans, $incomingPlans))
            + count(array_diff($existingQuantities, $incomingQuantities));
        DB::transaction(function () use ($run, $account, $snapshot, $deletedCount): void {
            $summary = $this->projection->project($account, $snapshot);
            $processed = 2 + $summary->plans->count() + $summary->quantities->count();
            $run->update(['status' => SyncRunStatus::Succeeded, 'processed_count' => $processed, 'upserted_count' => $processed, 'deleted_count' => $deletedCount, 'finished_at' => now()]);
            SyncCheckpoint::query()->updateOrCreate(['switch_account_id' => $account->getKey(), 'resource_type' => 'services'], ['last_sync_run_id' => $run->getKey(), 'cursor' => null, 'status' => ProjectionStatus::Healthy, 'last_successful_at' => now(), 'error_message' => null]);
        });
    }
}
