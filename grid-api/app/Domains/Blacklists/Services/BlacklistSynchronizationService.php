<?php

namespace App\Domains\Blacklists\Services;

use App\Domains\Blacklists\Contracts\SwitchBlacklistGateway;
use App\Domains\Blacklists\Models\SwitchBlacklist;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Illuminate\Support\Facades\DB;

class BlacklistSynchronizationService
{
    public function __construct(private readonly SwitchBlacklistGateway $gateway, private readonly BlacklistProjectionService $projection) {}
    public function handle(SyncRun $run): void
    {
        $run->update(['status' => SyncRunStatus::Running, 'started_at' => now(), 'finished_at' => null, 'error_code' => null, 'error_message' => null]);
        $account = $run->switchAccount()->firstOrFail(); $active = $this->gateway->activeIds($account); $snapshots = [];
        foreach ($this->gateway->all($account) as $snapshot) if (is_string($snapshot['id'] ?? null) && $snapshot['id'] !== '') $snapshots[$snapshot['id']] = $snapshot;
        DB::transaction(function () use ($account, $run, $active, $snapshots): void {
            foreach ($snapshots as $id => $snapshot) $this->projection->project($account, $snapshot, in_array($id, $active, true));
            $missing = SwitchBlacklist::query()->where('switch_account_id', $account->getKey())->when($snapshots !== [], fn ($q) => $q->whereNotIn('switch_resource_id', array_keys($snapshots)))->get();
            SwitchBlacklist::destroy($missing->modelKeys()); $processed = count($snapshots);
            $run->update(['status' => SyncRunStatus::Succeeded, 'processed_count' => $processed, 'upserted_count' => $processed, 'deleted_count' => $missing->count(), 'finished_at' => now()]);
            SyncCheckpoint::query()->updateOrCreate(['switch_account_id' => $account->getKey(), 'resource_type' => 'blacklists'], ['last_sync_run_id' => $run->getKey(), 'cursor' => null, 'status' => ProjectionStatus::Healthy, 'last_successful_at' => now(), 'error_message' => null]);
        });
    }
}
