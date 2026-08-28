<?php

namespace App\Domains\Faxes\Services;

use App\Domains\Faxes\Jobs\SyncSwitchFaxesJob;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StartFaxSyncService
{
    public function handle(SwitchAccount $account, User $user): SyncRun
    {
        [$run, $created] = Cache::lock("switch-sync-start:{$account->getKey()}:faxes", 10)->block(3, function () use ($account, $user): array { $active = $account->syncRuns()->where('resource_type', 'faxes')->whereIn('status', [SyncRunStatus::Queued->value, SyncRunStatus::Running->value])->where('created_at', '>=', now()->subMinutes(10))->latest()->first(); if ($active !== null) return [$active, false]; return DB::transaction(function () use ($account, $user): array { $run = $account->syncRuns()->create(['requested_by_user_id' => $user->getKey(), 'resource_type' => 'faxes', 'status' => SyncRunStatus::Queued]); SyncCheckpoint::query()->updateOrCreate(['switch_account_id' => $account->getKey(), 'resource_type' => 'faxes'], ['last_sync_run_id' => $run->getKey(), 'status' => ProjectionStatus::Syncing, 'error_message' => null]); return [$run, true]; }); });
        if ($created) SyncSwitchFaxesJob::dispatch((string) $run->getKey(), (string) $account->getKey()); return $run;
    }
}
