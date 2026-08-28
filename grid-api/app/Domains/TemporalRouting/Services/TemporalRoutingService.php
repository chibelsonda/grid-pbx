<?php

namespace App\Domains\TemporalRouting\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TemporalRoutingService
{
    public function __construct(private readonly TemporalRuleStatusService $status) {}

    /** @return LengthAwarePaginator<int, SwitchTemporalRule> */
    public function rules(SwitchAccount $account, ?string $search, int $perPage): LengthAwarePaginator
    {
        $paginator = $account->temporalRules()->when($search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))->orderBy('name')->paginate($perPage);
        $paginator->getCollection()->each(fn (SwitchTemporalRule $rule) => $this->attachRuleStatus($account, $rule));

        return $paginator;
    }

    /** @return LengthAwarePaginator<int, SwitchTemporalRuleSet> */
    public function sets(SwitchAccount $account, ?string $search, int $perPage): LengthAwarePaginator
    {
        $paginator = $account->temporalRuleSets()->withCount('rules')->with('rules.rule')->when($search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))->orderBy('name')->paginate($perPage);
        $paginator->getCollection()->each(function (SwitchTemporalRuleSet $set) use ($account): void {
            $set->setAttribute('effective_status', $this->status->ruleSet($account, $set));
            $set->unsetRelation('rules');
        });

        return $paginator;
    }

    public function findRule(SwitchAccount $account, string $id): SwitchTemporalRule
    {
        return $this->attachRuleStatus($account, $account->temporalRules()->where('id', $id)->firstOrFail());
    }

    public function findSet(SwitchAccount $account, string $id): SwitchTemporalRuleSet
    {
        $set = $account->temporalRuleSets()->where('id', $id)->with('rules.rule')->firstOrFail();
        $set->rules->each(function ($membership) use ($account): void {
            if ($membership->rule !== null) {
                $this->attachRuleStatus($account, $membership->rule);
            }
        });
        $set->setAttribute('effective_status', $this->status->ruleSet($account, $set));

        return $set;
    }

    /** @return array<string, mixed> */
    public function options(SwitchAccount $account): array
    {
        return ['rules' => $account->temporalRules()->orderBy('name')->get()->map(fn ($rule) => ['id' => $rule->id, 'label' => $rule->name, 'detail' => $rule->cycle])->values()->all()];
    }

    private function attachRuleStatus(SwitchAccount $account, SwitchTemporalRule $rule): SwitchTemporalRule
    {
        $rule->setAttribute('effective_status', $this->status->rule($account, $rule));

        return $rule;
    }
}
