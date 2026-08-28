<?php

namespace App\Domains\Recordings\Services;

use App\Domains\Recordings\Contracts\SwitchRecordingGateway;
use App\Domains\Recordings\Models\SwitchRecording;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class RecordingSynchronizationService
{
    public function __construct(private readonly SwitchRecordingGateway $gateway, private readonly RecordingProjectionService $projection) {}
    public function handle(SyncRun $run): void
    {
        $windowDays = (int) config('switch.recording_import_window_days'); if ($windowDays < 1 || $windowDays > 93) throw new UnexpectedValueException('Switch recording import window must be between 1 and 93 days.');
        $run->update(['status' => SyncRunStatus::Running, 'started_at' => now(), 'finished_at' => null, 'error_code' => null, 'error_message' => null]);
        $account = $run->switchAccount()->firstOrFail(); $to = CarbonImmutable::now('UTC'); $from = $to->subDays($windowDays); $snapshots = [];
        foreach ($this->gateway->all($account, $from, $to) as $snapshot) if (is_string($snapshot['switch_resource_id'] ?? null) && $snapshot['switch_resource_id'] !== '') $snapshots[$snapshot['switch_resource_id']] = $snapshot;
        DB::transaction(function () use ($account, $run, $from, $to, $snapshots): void {
            foreach ($snapshots as $snapshot) $this->projection->project($account, $snapshot);
            $missing = SwitchRecording::query()->where('switch_account_id', $account->getKey())->whereBetween('started_at', [$from, $to])->when($snapshots !== [], fn ($query) => $query->whereNotIn('switch_resource_id', array_keys($snapshots)))->get(); SwitchRecording::destroy($missing->modelKeys()); $processed = count($snapshots);
            $run->update(['status' => SyncRunStatus::Succeeded, 'processed_count' => $processed, 'upserted_count' => $processed, 'deleted_count' => $missing->count(), 'finished_at' => now()]);
            SyncCheckpoint::query()->updateOrCreate(['switch_account_id' => $account->getKey(), 'resource_type' => 'recordings'], ['last_sync_run_id' => $run->getKey(), 'cursor' => null, 'status' => ProjectionStatus::Healthy, 'last_successful_at' => now(), 'error_message' => null]);
        });
    }
}
