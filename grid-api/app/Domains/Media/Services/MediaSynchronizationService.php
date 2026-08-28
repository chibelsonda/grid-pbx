<?php

namespace App\Domains\Media\Services;

use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\Media\Contracts\SwitchMediaGateway;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Illuminate\Support\Facades\DB;

class MediaSynchronizationService
{
    public function __construct(
        private readonly SwitchMediaGateway $gateway,
        private readonly MediaProjectionService $projection,
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
            $resourceId = $snapshot['id'] ?? null;

            if (is_string($resourceId) && $resourceId !== '') {
                $snapshots[$resourceId] = $snapshot;
            }
        }

        $musicOnHoldResourceId = $this->gateway->accountMusicOnHold($account);

        DB::transaction(function () use ($account, $musicOnHoldResourceId, $run, $snapshots): void {
            $projected = [];

            foreach ($snapshots as $resourceId => $snapshot) {
                $projected[$resourceId] = $this->projection->project($account, $snapshot);
            }

            $missing = SwitchMedia::query()
                ->where('switch_account_id', $account->getKey())
                ->when($snapshots !== [], fn ($query) => $query->whereNotIn('switch_resource_id', array_keys($snapshots)))
                ->get();
            SwitchMedia::destroy($missing->modelKeys());
            $account->update([
                'music_on_hold_media_id' => $musicOnHoldResourceId !== null && isset($projected[$musicOnHoldResourceId])
                    ? $projected[$musicOnHoldResourceId]->getKey()
                    : null,
            ]);
            $this->callflowReferences->refresh($account);
            $run->update([
                'status' => SyncRunStatus::Succeeded,
                'processed_count' => count($snapshots),
                'upserted_count' => count($snapshots),
                'deleted_count' => $missing->count(),
                'finished_at' => now(),
            ]);
            SyncCheckpoint::query()->updateOrCreate([
                'switch_account_id' => $account->getKey(),
                'resource_type' => 'media',
            ], [
                'last_sync_run_id' => $run->getKey(),
                'cursor' => null,
                'status' => ProjectionStatus::Healthy,
                'last_successful_at' => now(),
                'error_message' => null,
            ]);
        });
    }
}
