<?php

namespace App\Domains\TemporalRouting\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TemporalRoutingService
{
    /** @return LengthAwarePaginator<int, SwitchTemporalRule> */
    public function rules(SwitchAccount $account, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $account->temporalRules()->when($search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))->orderBy('name')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<int, SwitchTemporalRuleSet> */
    public function sets(SwitchAccount $account, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $account->temporalRuleSets()->withCount('rules')->when($search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))->orderBy('name')->paginate($perPage);
    }

    public function findRule(SwitchAccount $account, string $id): SwitchTemporalRule
    {
        return $account->temporalRules()->where('id', $id)->firstOrFail();
    }

    public function findSet(SwitchAccount $account, string $id): SwitchTemporalRuleSet
    {
        return $account->temporalRuleSets()->where('id', $id)->with('rules.rule')->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function options(SwitchAccount $account): array
    {
        return ['rules' => $account->temporalRules()->orderBy('name')->get()->map(fn ($rule) => ['id' => $rule->id, 'label' => $rule->name, 'detail' => $rule->cycle])->values()->all()];
    }
}
