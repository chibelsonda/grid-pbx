<?php

namespace App\Domains\TemporalRouting\Services;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use App\Domains\TemporalRouting\Jobs\SyncSwitchTemporalRoutingJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StartTemporalRoutingSyncService
{
    public function handle(SwitchAccount $account, User $user): SyncRun
    {
        [$run, $created] = Cache::lock("switch-sync-start:{$account->getKey()}:temporal_routing", 10)->block(3, function () use ($account, $user) {
            $active = $account->syncRuns()->where('resource_type', 'temporal_routing')->whereIn('status', [SyncRunStatus::Queued->value, SyncRunStatus::Running->value])->where('created_at', '>=', now()->subMinutes(10))->latest()->first();
            if ($active) {
                return [$active, false];
            }

            return DB::transaction(function () use ($account, $user) {
                $run = $account->syncRuns()->create(['requested_by_user_id' => $user->getKey(), 'resource_type' => 'temporal_routing', 'status' => SyncRunStatus::Queued]);
                SyncCheckpoint::query()->updateOrCreate(['switch_account_id' => $account->getKey(), 'resource_type' => 'temporal_routing'], ['last_sync_run_id' => $run->getKey(), 'status' => ProjectionStatus::Syncing, 'error_message' => null]);

                return [$run, true];
            });
        });
        if ($created) {
            SyncSwitchTemporalRoutingJob::dispatch((string) $run->getKey(), (string) $account->getKey());
        }

        return $run;
    }
}
