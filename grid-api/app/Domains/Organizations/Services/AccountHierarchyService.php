<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Eloquent\Collection;

class AccountHierarchyService
{
    /** @return array<string, mixed> */
    public function hierarchy(SwitchAccount $account): array
    {
        $accounts = $this->organizationAccounts($account);
        $indexed = $accounts->keyBy(fn (SwitchAccount $item): int => $item->getKey());
        $ancestors = $this->ancestors($account, $indexed);
        $children = $accounts
            ->where('parent_account_id', $account->getKey())
            ->sortBy('name')
            ->values();
        $descendants = $this->descendants($account, $accounts);
        $switchDescendantsCount = max(0, (int) ($account->descendants_count ?? 0));

        return [
            'account' => $this->account($account),
            'parent' => $ancestors->last() === null ? null : $this->account($ancestors->last()),
            'ancestors' => $ancestors->map(fn (SwitchAccount $item): array => $this->account($item))->all(),
            'children' => $children->map(fn (SwitchAccount $item): array => $this->account($item))->all(),
            'descendants' => $descendants->map(fn (SwitchAccount $item): array => $this->account($item))->all(),
            'coverage' => [
                'switch_descendants_count' => $switchDescendantsCount,
                'projected_descendants_count' => $descendants->count(),
                'unresolved_descendants_count' => max(0, $switchDescendantsCount - $descendants->count()),
                'parent_projected' => $account->parent_switch_account_id === null
                    || $account->parent_account_id !== null,
            ],
            'projection' => [
                'last_synced_at' => $account->hierarchy_synced_at?->toIso8601String(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function reseller(SwitchAccount $account): array
    {
        $summary = $account->serviceSummary()
            ->with('billingResellerAccount:account_id,id,name,realm,is_enabled,is_reseller,is_superduper_admin,billing_mode,descendants_count')
            ->first();
        $billingAccount = $summary?->billingResellerAccount;

        return [
            'account' => $this->account($account),
            'billing_reseller' => $billingAccount === null ? null : $this->account($billingAccount),
            'billing_reseller_projected' => $summary === null
                ? null
                : $summary->billing_reseller_switch_account_id === null || $billingAccount !== null,
            'service_projection_last_synced_at' => $summary?->last_synced_at?->toIso8601String(),
            'mutations' => [
                'promote' => ['available' => false, 'reason' => 'platform_policy_required'],
                'demote' => ['available' => false, 'reason' => 'platform_policy_required'],
            ],
        ];
    }

    /** @return Collection<int, SwitchAccount> */
    private function organizationAccounts(SwitchAccount $account): Collection
    {
        return SwitchAccount::query()
            ->where('organization_id', $account->organization_id)
            ->get([
                'account_id',
                'id',
                'organization_id',
                'parent_account_id',
                'parent_switch_account_id',
                'name',
                'realm',
                'is_enabled',
                'is_reseller',
                'is_superduper_admin',
                'billing_mode',
                'descendants_count',
                'hierarchy_synced_at',
            ]);
    }

    /**
     * @param  Collection<int, SwitchAccount>  $indexed
     * @return Collection<int, SwitchAccount>
     */
    private function ancestors(SwitchAccount $account, Collection $indexed): Collection
    {
        $ancestors = new Collection;
        $visited = [$account->getKey() => true];
        $parentId = $account->parent_account_id;

        while (is_int($parentId) && ! isset($visited[$parentId])) {
            $parent = $indexed->get($parentId);

            if (! $parent instanceof SwitchAccount) {
                break;
            }

            $visited[$parentId] = true;
            $ancestors->prepend($parent);
            $parentId = $parent->parent_account_id;
        }

        return $ancestors;
    }

    /**
     * @param  Collection<int, SwitchAccount>  $accounts
     * @return Collection<int, SwitchAccount>
     */
    private function descendants(SwitchAccount $account, Collection $accounts): Collection
    {
        $descendants = new Collection;
        $pending = [$account->getKey()];
        $visited = [$account->getKey() => true];

        while ($pending !== []) {
            $parentId = array_shift($pending);

            foreach ($accounts->where('parent_account_id', $parentId) as $child) {
                if (isset($visited[$child->getKey()])) {
                    continue;
                }

                $visited[$child->getKey()] = true;
                $descendants->push($child);
                $pending[] = $child->getKey();
            }
        }

        return $descendants->sortBy('name')->values();
    }

    /** @return array<string, mixed> */
    private function account(SwitchAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'realm' => $account->realm,
            'enabled' => $account->is_enabled,
            'is_reseller' => $account->is_reseller,
            'is_superduper_admin' => $account->is_superduper_admin,
            'billing_mode' => $account->billing_mode,
            'descendants_count' => max(0, (int) ($account->descendants_count ?? 0)),
        ];
    }
}
