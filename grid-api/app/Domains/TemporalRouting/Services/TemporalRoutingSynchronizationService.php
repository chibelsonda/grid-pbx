<?php

namespace App\Domains\TemporalRouting\Services;

use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleGateway;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleSetGateway;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use Illuminate\Support\Facades\DB;

class TemporalRoutingSynchronizationService
{
    public function __construct(private readonly SwitchTemporalRuleGateway $rules, private readonly SwitchTemporalRuleSetGateway $sets, private readonly TemporalRuleProjectionService $ruleProjection, private readonly TemporalRuleSetProjectionService $setProjection, private readonly CallflowReferenceResolver $callflowReferences) {}

    public function handle(SyncRun $run): void
    {
        $run->update(['status' => SyncRunStatus::Running, 'started_at' => now(), 'finished_at' => null, 'error_code' => null, 'error_message' => null]);
        $account = $run->switchAccount()->firstOrFail();
        $rules = [];
        $sets = [];
        foreach ($this->rules->all($account) as $snapshot) {
            if (is_string($snapshot['id'] ?? null) && $snapshot['id'] !== '') {
                $rules[$snapshot['id']] = $snapshot;
            }
        }
        foreach ($this->sets->all($account) as $snapshot) {
            if (is_string($snapshot['id'] ?? null) && $snapshot['id'] !== '') {
                $sets[$snapshot['id']] = $snapshot;
            }
        }
        DB::transaction(function () use ($account, $run, $rules, $sets): void {
            foreach ($rules as $snapshot) {
                $this->ruleProjection->project($account, $snapshot);
            }
            foreach ($sets as $snapshot) {
                $this->setProjection->project($account, $snapshot);
            }
            $this->setProjection->reconcileRules($account);
            $missingSets = SwitchTemporalRuleSet::query()->where('switch_account_id', $account->getKey())->when($sets !== [], fn ($q) => $q->whereNotIn('switch_resource_id', array_keys($sets)))->get();
            SwitchTemporalRuleSet::destroy($missingSets->modelKeys());
            $missingRules = SwitchTemporalRule::query()->where('switch_account_id', $account->getKey())->when($rules !== [], fn ($q) => $q->whereNotIn('switch_resource_id', array_keys($rules)))->get();
            SwitchTemporalRule::destroy($missingRules->modelKeys());
            $this->callflowReferences->refresh($account);
            $processed = count($rules) + count($sets);
            $run->update(['status' => SyncRunStatus::Succeeded, 'processed_count' => $processed, 'upserted_count' => $processed, 'deleted_count' => $missingSets->count() + $missingRules->count(), 'finished_at' => now()]);
            SyncCheckpoint::query()->updateOrCreate(['switch_account_id' => $account->getKey(), 'resource_type' => 'temporal_routing'], ['last_sync_run_id' => $run->getKey(), 'cursor' => null, 'status' => ProjectionStatus::Healthy, 'last_successful_at' => now(), 'error_message' => null]);
        });
    }
}
