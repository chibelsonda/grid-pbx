<?php

namespace App\Domains\Blacklists\Services;

use App\Domains\Blacklists\Jobs\SyncSwitchBlacklistsJob;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StartBlacklistSyncService
{
    public function handle(SwitchAccount $account, User $requestedBy): SyncRun
    {
        [$run, $created] = Cache::lock("switch-sync-start:{$account->getKey()}:blacklists", 10)->block(3, function () use ($account, $requestedBy): array {
            $active = $account->syncRuns()->where('resource_type', 'blacklists')->whereIn('status', [SyncRunStatus::Queued->value, SyncRunStatus::Running->value])->where('created_at', '>=', now()->subMinutes(10))->latest()->first(); if ($active !== null) return [$active, false];
            return DB::transaction(function () use ($account, $requestedBy): array { $run = $account->syncRuns()->create(['requested_by_user_id' => $requestedBy->getKey(), 'resource_type' => 'blacklists', 'status' => SyncRunStatus::Queued]); SyncCheckpoint::query()->updateOrCreate(['switch_account_id' => $account->getKey(), 'resource_type' => 'blacklists'], ['last_sync_run_id' => $run->getKey(), 'status' => ProjectionStatus::Syncing, 'error_message' => null]); return [$run, true]; });
        });
        if ($created) SyncSwitchBlacklistsJob::dispatch((string) $run->getKey(), (string) $account->getKey());
        return $run;
    }
}
