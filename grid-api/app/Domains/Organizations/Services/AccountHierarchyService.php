<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Models\SwitchServiceQuantity;
use Illuminate\Database\Eloquent\Collection;

class AccountHierarchyService
{
    /** @return array<string, mixed> */
    public function hierarchy(SwitchAccount $account): array
    {
        $accounts = $this->organizationAccounts($account);
        $indexed = $accounts->keyBy(fn (SwitchAccount $item): int => $item->getKey());
        $current = $indexed->get($account->getKey(), $account);
        $ancestors = $this->ancestors($current, $indexed);
        $children = $accounts
            ->where('parent_account_id', $current->getKey())
            ->sortBy('name')
            ->values();
        $descendants = $this->descendants($current, $accounts);
        $scope = (new Collection([$current]))->concat($descendants)->values();
        $switchDescendantsCount = max(0, (int) ($current->descendants_count ?? 0));
        $unresolvedDescendantsCount = max(0, $switchDescendantsCount - $descendants->count());
        $parentProjected = $current->parent_switch_account_id === null
            || $current->parent_account_id !== null;

        return [
            'account' => $this->account($current),
            'parent' => $ancestors->last() === null ? null : $this->account($ancestors->last()),
            'ancestors' => $ancestors->map(fn (SwitchAccount $item): array => $this->account($item))->all(),
            'children' => $children->map(fn (SwitchAccount $item): array => $this->account($item))->all(),
            'descendants' => $descendants->map(fn (SwitchAccount $item): array => $this->account($item))->all(),
            'coverage' => [
                'switch_descendants_count' => $switchDescendantsCount,
                'projected_descendants_count' => $descendants->count(),
                'unresolved_descendants_count' => $unresolvedDescendantsCount,
                'parent_projected' => $parentProjected,
            ],
            'projection' => [
                'last_synced_at' => $current->hierarchy_synced_at?->toIso8601String(),
            ],
            'portfolio' => $this->portfolio($scope, $unresolvedDescendantsCount),
            'mutation_preflight' => $this->mutationPreflight(
                $current,
                $scope,
                $accounts,
                $unresolvedDescendantsCount,
                $parentProjected,
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function reseller(SwitchAccount $account): array
    {
        $summary = $account->serviceSummary()
            ->with('billingResellerAccount:account_id,id,name,realm,is_enabled,is_reseller,is_superduper_admin,billing_mode,descendants_count')
            ->first();
        $account->setRelation('serviceSummary', $summary);
        $account->loadMissing(
            'serviceSyncCheckpoint:sync_checkpoint_id,switch_account_id,status,last_successful_at',
        );
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
            'administration' => [
                'account_creation_available' => false,
                'account_move_available' => false,
                'account_deletion_available' => false,
                'limit_mutations_available' => false,
                'service_plan_mutations_available' => false,
                'service_override_mutations_available' => false,
                'top_up_available' => false,
                'switch_service_synchronization_available' => false,
                'switch_service_reconciliation_available' => false,
            ],
        ];
    }

    /** @return Collection<int, SwitchAccount> */
    private function organizationAccounts(SwitchAccount $account): Collection
    {
        return SwitchAccount::query()
            ->where('organization_id', $account->organization_id)
            ->with([
                'serviceSummary' => fn ($query) => $query
                    ->select([
                        'service_summary_id',
                        'switch_account_id',
                        'billing_reseller_account_id',
                        'billing_reseller_switch_account_id',
                        'last_synced_at',
                        'sync_status',
                        'due_today',
                        'recurring_amount',
                    ])
                    ->with('billingResellerAccount:account_id,id,name,realm'),
                'serviceSyncCheckpoint:sync_checkpoint_id,switch_account_id,status,last_successful_at',
            ])
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
        $summary = $account->serviceSummary;
        $checkpoint = $account->serviceSyncCheckpoint;
        $billingAccount = $summary?->billingResellerAccount;

        return [
            'id' => $account->id,
            'name' => $account->name,
            'realm' => $account->realm,
            'enabled' => $account->is_enabled,
            'is_reseller' => $account->is_reseller,
            'is_superduper_admin' => $account->is_superduper_admin,
            'billing_mode' => $account->billing_mode,
            'descendants_count' => max(0, (int) ($account->descendants_count ?? 0)),
            'service_projection' => [
                'status' => $checkpoint?->status->value ?? $summary?->sync_status->value ?? 'stale',
                'last_successful_at' => $checkpoint?->last_successful_at?->toIso8601String()
                    ?? $summary?->last_synced_at?->toIso8601String(),
                'billing_reseller' => $billingAccount === null ? null : [
                    'id' => $billingAccount->id,
                    'name' => $billingAccount->name,
                    'realm' => $billingAccount->realm,
                ],
                'billing_reseller_projected' => $summary === null
                    ? null
                    : $summary->billing_reseller_switch_account_id === null || $billingAccount !== null,
            ],
        ];
    }

    /**
     * @param  Collection<int, SwitchAccount>  $scope
     * @return array<string, mixed>
     */
    private function portfolio(Collection $scope, int $unresolvedDescendantsCount): array
    {
        $projected = $scope->filter(
            fn (SwitchAccount $account): bool => $account->serviceSummary !== null,
        );
        $healthy = $scope->filter(
            fn (SwitchAccount $account): bool => $this->projectionStatus($account) === 'healthy',
        );
        $missingProjections = $scope->reject(
            fn (SwitchAccount $account): bool => $account->serviceSummary !== null,
        );
        $unhealthyProjections = $projected->filter(
            fn (SwitchAccount $account): bool => $this->projectionStatus($account) !== 'healthy',
        );
        $unresolvedBillingOwnerAccounts = $projected->filter(function (SwitchAccount $account): bool {
            $summary = $account->serviceSummary;

            return $summary?->billing_reseller_switch_account_id !== null
                && $summary->billingResellerAccount === null;
        });
        $unresolvedBillingOwners = $unresolvedBillingOwnerAccounts->count();
        $scopeIds = $scope->modelKeys();
        $quantities = $scopeIds === []
            ? collect()
            : SwitchServiceQuantity::query()
                ->whereIn('switch_account_id', $scopeIds)
                ->select(['scope', 'category', 'item'])
                ->selectRaw('SUM(quantity) AS total_quantity')
                ->groupBy('scope', 'category', 'item')
                ->orderBy('scope')
                ->orderBy('category')
                ->orderBy('item')
                ->get()
                ->map(fn (SwitchServiceQuantity $quantity): array => [
                    'scope' => $quantity->scope,
                    'category' => $quantity->category,
                    'item' => $quantity->item,
                    'quantity' => (float) $quantity->getAttribute('total_quantity'),
                ])
                ->values();
        $warnings = collect([
            $this->warning(
                'missing_service_projection',
                $missingProjections->count(),
                'Managed accounts do not have a service projection.',
                'Synchronize services for each listed account, then investigate any projection failure that remains.',
                $missingProjections,
            ),
            $this->warning(
                'service_projection_attention',
                $unhealthyProjections->count(),
                'Projected service records require synchronization or investigation.',
                'Synchronize services for each listed account. If an error remains, review the safe synchronization status and server logs.',
                $unhealthyProjections,
            ),
            $this->warning(
                'unresolved_billing_owner',
                $unresolvedBillingOwners,
                'Billing reseller references are not mapped to managed GridPBX accounts.',
                'Project or onboard the referenced billing reseller before changing service ownership.',
                $unresolvedBillingOwnerAccounts,
            ),
            $this->warning(
                'unresolved_hierarchy_descendant',
                $unresolvedDescendantsCount,
                'Switch descendants are not mapped to this GridPBX organization.',
                'Use Review descendants to inspect eligible accounts and complete the confirmed onboarding workflow.',
                new Collection,
            ),
        ])->filter()->values();

        return [
            'accounts' => [
                'total' => $scope->count(),
                'projected' => $projected->count(),
                'healthy' => $healthy->count(),
                'attention' => $scope->count() - $healthy->count(),
            ],
            'billing_ownership' => [
                'projected' => $projected->count() - $unresolvedBillingOwners,
                'unresolved' => $unresolvedBillingOwners,
            ],
            'billing' => [
                'due_today' => (float) $projected->sum(
                    fn (SwitchAccount $account): float => (float) $account->serviceSummary?->due_today,
                ),
                'recurring_amount' => (float) $projected->sum(
                    fn (SwitchAccount $account): float => (float) $account->serviceSummary?->recurring_amount,
                ),
            ],
            'quantities' => $quantities->all(),
            'warnings' => $warnings->all(),
        ];
    }

    /**
     * @param  Collection<int, SwitchAccount>  $scope
     * @param  Collection<int, SwitchAccount>  $organizationAccounts
     * @return array<string, mixed>
     */
    private function mutationPreflight(
        SwitchAccount $account,
        Collection $scope,
        Collection $organizationAccounts,
        int $unresolvedDescendantsCount,
        bool $parentProjected,
    ): array {
        $operation = $account->is_reseller ? 'demote' : 'promote';
        $billingDependents = $organizationAccounts
            ->filter(function (SwitchAccount $candidate) use ($account): bool {
                return $candidate->getKey() !== $account->getKey()
                    && $candidate->serviceSummary?->billing_reseller_account_id === $account->getKey();
            })
            ->values();
        $currentAccount = new Collection([$account]);
        $checks = [
            $this->preflightCheck(
                'service_projection_healthy',
                $this->projectionStatus($account) === 'healthy',
                $this->projectionStatus($account) === 'healthy' ? 0 : 1,
                'The selected account must have a healthy service projection.',
                'Synchronize services for the selected account and resolve any remaining projection error.',
                $this->projectionStatus($account) === 'healthy' ? new Collection : $currentAccount,
            ),
            $this->preflightCheck(
                'hierarchy_complete',
                $unresolvedDescendantsCount === 0,
                $unresolvedDescendantsCount,
                'Every Switch descendant must be mapped before a reseller role can change.',
                'Use Review descendants to onboard or explicitly account for every unmanaged Switch descendant.',
                new Collection,
            ),
        ];

        if ($operation === 'demote') {
            $projectedDescendants = max(0, $scope->count() - 1);
            $reportedDescendants = max(0, (int) ($account->descendants_count ?? 0));
            $checks[] = $this->preflightCheck(
                'no_descendants',
                $projectedDescendants === 0 && $reportedDescendants === 0,
                max($projectedDescendants, $reportedDescendants),
                'A reseller with descendants cannot be demoted safely.',
                'Move or remove each descendant through an approved account lifecycle before requesting demotion.',
                $scope->skip(1)->values(),
            );
            $checks[] = $this->preflightCheck(
                'no_billing_dependents',
                $billingDependents->isEmpty(),
                $billingDependents->count(),
                'Accounts that bill through this reseller must be reassigned first.',
                'Reassign billing ownership through an approved service-management workflow before requesting demotion.',
                $billingDependents,
            );
        } else {
            $billingOwnershipProjected = $account->serviceSummary !== null
                && ($account->serviceSummary->billing_reseller_switch_account_id === null
                    || $account->serviceSummary->billingResellerAccount !== null);
            $checks[] = $this->preflightCheck(
                'parent_projected',
                $parentProjected,
                $parentProjected ? 0 : 1,
                'The parent account must be projected before promotion.',
                'Synchronize or onboard the parent account before requesting promotion.',
                new Collection,
            );
            $checks[] = $this->preflightCheck(
                'billing_ownership_projected',
                $billingOwnershipProjected,
                $billingOwnershipProjected ? 0 : 1,
                'Billing ownership must resolve to a managed account before promotion.',
                'Project the billing reseller and synchronize services for the selected account.',
                $billingOwnershipProjected ? new Collection : $currentAccount,
            );
        }

        $operationallyReady = collect($checks)->every(
            fn (array $check): bool => $check['passed'],
        );
        $checks[] = $this->preflightCheck(
            'platform_policy_available',
            false,
            1,
            'Platform policy, explicit confirmation, auditing, and recovery are still required.',
            'Obtain an approved reseller-role policy and recovery contract. GridPBX intentionally provides no mutation control yet.',
            new Collection,
        );

        return [
            'operation' => $operation,
            'operationally_ready' => $operationallyReady,
            'mutation_available' => false,
            'checks' => $checks,
        ];
    }

    private function projectionStatus(SwitchAccount $account): string
    {
        return $account->serviceSyncCheckpoint?->status->value
            ?? $account->serviceSummary?->sync_status->value
            ?? 'stale';
    }

    /**
     * @param  Collection<int, SwitchAccount>  $affectedAccounts
     * @return array<string, mixed>|null
     */
    private function warning(
        string $code,
        int $count,
        string $message,
        string $guidance,
        Collection $affectedAccounts,
    ): ?array {
        if ($count === 0) {
            return null;
        }

        return [
            'code' => $code,
            'count' => $count,
            'message' => $message,
            'guidance' => $guidance,
            'affected_accounts' => $this->affectedAccounts($affectedAccounts),
        ];
    }

    /**
     * @param  Collection<int, SwitchAccount>  $affectedAccounts
     * @return array<string, mixed>
     */
    private function preflightCheck(
        string $code,
        bool $passed,
        int $count,
        string $message,
        string $guidance,
        Collection $affectedAccounts,
    ): array {
        return [
            'code' => $code,
            'passed' => $passed,
            'count' => $count,
            'message' => $message,
            'guidance' => $guidance,
            'affected_accounts' => $this->affectedAccounts($affectedAccounts),
        ];
    }

    /**
     * @param  Collection<int, SwitchAccount>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function affectedAccounts(Collection $accounts): array
    {
        return $accounts
            ->map(fn (SwitchAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'realm' => $account->realm,
                'service_projection_status' => $this->projectionStatus($account),
            ])
            ->values()
            ->all();
    }
}
