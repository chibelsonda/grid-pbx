<?php

namespace App\Domains\Faxes\Services;

use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\Faxes\Contracts\SwitchFaxBoxGateway;
use App\Domains\Faxes\Contracts\SwitchFaxGateway;
use App\Domains\Faxes\Models\SwitchFax;
use App\Domains\Faxes\Models\SwitchFaxBox;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class FaxSynchronizationService
{
    public function __construct(private readonly SwitchFaxBoxGateway $boxes, private readonly SwitchFaxGateway $faxes, private readonly FaxBoxProjectionService $boxProjection, private readonly FaxProjectionService $faxProjection, private readonly CallflowReferenceResolver $callflowReferences) {}
    public function handle(SyncRun $run): void
    {
        $windowDays = (int) config('switch.fax_import_window_days'); if ($windowDays < 1 || $windowDays > 93) throw new UnexpectedValueException('Switch fax import window must be between 1 and 93 days.');
        $run->update(['status' => SyncRunStatus::Running, 'started_at' => now(), 'finished_at' => null, 'error_code' => null, 'error_message' => null]); $account = $run->switchAccount()->firstOrFail(); $to = CarbonImmutable::now('UTC'); $from = $to->subDays($windowDays); $boxSnapshots = []; $faxSnapshots = [];
        foreach ($this->boxes->all($account) as $snapshot) if (is_string($snapshot['id'] ?? null) && $snapshot['id'] !== '') $boxSnapshots[$snapshot['id']] = $snapshot;
        foreach (['inbox', 'outbox'] as $folder) foreach ($this->faxes->all($account, $folder, $from, $to) as $snapshot) if (is_string($snapshot['switch_resource_id'] ?? null) && $snapshot['switch_resource_id'] !== '') $faxSnapshots[$folder.':'.$snapshot['switch_resource_id']] = $snapshot;
        DB::transaction(function () use ($account, $run, $from, $to, $boxSnapshots, $faxSnapshots): void {
            foreach ($boxSnapshots as $snapshot) $this->boxProjection->project($account, $snapshot); foreach ($faxSnapshots as $snapshot) $this->faxProjection->project($account, $snapshot);
            $missingBoxes = SwitchFaxBox::query()->where('switch_account_id', $account->getKey())->when($boxSnapshots !== [], fn ($query) => $query->whereNotIn('switch_resource_id', array_keys($boxSnapshots)))->get(); SwitchFaxBox::destroy($missingBoxes->modelKeys());
            $seenByFolder = ['inbox' => [], 'outbox' => []]; foreach ($faxSnapshots as $snapshot) $seenByFolder[$snapshot['folder']][] = $snapshot['switch_resource_id']; $missingFaxCount = 0;
            foreach ($seenByFolder as $folder => $seen) { $missing = SwitchFax::query()->where('switch_account_id', $account->getKey())->where('folder', $folder)->whereBetween('switch_created_at', [$from, $to])->when($seen !== [], fn ($query) => $query->whereNotIn('switch_resource_id', $seen))->get(); $missingFaxCount += $missing->count(); SwitchFax::destroy($missing->modelKeys()); }
            $this->callflowReferences->refresh($account); $processed = count($boxSnapshots) + count($faxSnapshots);
            $run->update(['status' => SyncRunStatus::Succeeded, 'processed_count' => $processed, 'upserted_count' => $processed, 'deleted_count' => $missingBoxes->count() + $missingFaxCount, 'finished_at' => now()]);
            SyncCheckpoint::query()->updateOrCreate(['switch_account_id' => $account->getKey(), 'resource_type' => 'faxes'], ['last_sync_run_id' => $run->getKey(), 'cursor' => null, 'status' => ProjectionStatus::Healthy, 'last_successful_at' => now(), 'error_message' => null]);
        });
    }
}
