<?php

namespace App\Domains\TemporalRouting\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use UnexpectedValueException;

class TemporalRuleSetProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redact) {}

    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchTemporalRuleSet
    {
        $id = is_string($snapshot['id'] ?? null) ? $snapshot['id'] : null;
        $name = is_string($snapshot['name'] ?? null) ? $snapshot['name'] : null;
        if (empty($id) || empty($name)) {
            throw new UnexpectedValueException('Switch temporal rule set response is missing required metadata.');
        }
        $set = SwitchTemporalRuleSet::withTrashed()->firstOrNew(['switch_account_id' => $account->getKey(), 'switch_resource_id' => $id]);
        $set->fill(['name' => $name, 'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => $set->exists ? $set->projection_version + 1 : 1, 'switch_json' => $this->redact->handle($snapshot)]);
        $set->deleted_at = null;
        $set->save();
        $set->rules()->increment('position', 10000);
        $seen = [];
        foreach (is_array($snapshot['temporal_rules'] ?? null) ? $snapshot['temporal_rules'] : [] as $position => $ruleId) {
            if (! is_string($ruleId) || $ruleId === '') {
                continue;
            }
            $membership = $set->rules()->updateOrCreate(['switch_rule_resource_id' => $ruleId], ['switch_temporal_rule_id' => $account->temporalRules()->where('switch_resource_id', $ruleId)->value('temporal_rule_id'), 'position' => $position]);
            $seen[] = $membership->getKey();
        }
        $set->rules()->when($seen !== [], fn ($query) => $query->whereNotIn('temporal_rule_set_rule_id', $seen))->delete();
        if ($seen === []) {
            $set->rules()->delete();
        }

        return $set->load('rules.rule');
    }

    public function reconcileRules(SwitchAccount $account): void
    {
        $account->temporalRuleSets()->with('rules')->get()->each(function ($set) use ($account): void {
            foreach ($set->rules as $membership) {
                $membership->update(['switch_temporal_rule_id' => $account->temporalRules()->where('switch_resource_id', $membership->switch_rule_resource_id)->value('temporal_rule_id')]);
            }
        });
    }
}
