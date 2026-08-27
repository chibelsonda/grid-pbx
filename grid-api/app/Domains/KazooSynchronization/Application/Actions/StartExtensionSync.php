<?php

namespace App\Domains\KazooSynchronization\Application\Actions;

use App\Domains\IdentityAccess\Infrastructure\Models\User;
use App\Domains\KazooSynchronization\Application\Jobs\SyncKazooExtensionsJob;
use App\Domains\KazooSynchronization\Domain\ProjectionStatus;
use App\Domains\KazooSynchronization\Domain\SyncRunStatus;
use App\Domains\KazooSynchronization\Infrastructure\Models\SyncCheckpoint;
use App\Domains\KazooSynchronization\Infrastructure\Models\SyncRun;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StartExtensionSync
{
    public function handle(KazooAccount $account, User $requestedBy): SyncRun
    {
        [$run, $created] = Cache::lock("kazoo-sync-start:{$account->getKey()}:extensions", 10)
            ->block(3, function () use ($account, $requestedBy): array {
                $activeRun = $account->syncRuns()
                    ->where('resource_type', 'extensions')
                    ->whereIn('status', [SyncRunStatus::Queued->value, SyncRunStatus::Running->value])
                    ->where('created_at', '>=', now()->subMinutes(10))
                    ->latest()
                    ->first();

                if ($activeRun !== null) {
                    return [$activeRun, false];
                }

                return DB::transaction(function () use ($account, $requestedBy): array {
                    $run = $account->syncRuns()->create([
                        'requested_by_user_id' => $requestedBy->getKey(),
                        'resource_type' => 'extensions',
                        'status' => SyncRunStatus::Queued,
                    ]);

                    SyncCheckpoint::query()->updateOrCreate([
                        'kazoo_account_id' => $account->getKey(),
                        'resource_type' => 'extensions',
                    ], [
                        'last_sync_run_id' => $run->getKey(),
                        'status' => ProjectionStatus::Syncing,
                        'error_message' => null,
                    ]);

                    return [$run, true];
                });
            });

        if ($created) {
            SyncKazooExtensionsJob::dispatch((string) $run->getKey(), (string) $account->getKey());
        }

        return $run;
    }
}
