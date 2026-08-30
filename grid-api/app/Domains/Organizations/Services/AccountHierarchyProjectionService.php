<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\Models\SwitchAccount;

class AccountHierarchyProjectionService
{
    /**
     * Project hierarchy only for accounts that GridPBX already manages.
     *
     * @param  array<string, mixed>  $accountSnapshot
     * @param  list<array<string, mixed>>  $descendants
     */
    public function project(SwitchAccount $account, array $accountSnapshot, array $descendants): void
    {
        $this->projectParent($account, $this->parentId($accountSnapshot));

        foreach ($descendants as $descendant) {
            $switchAccountId = $this->string($descendant['id'] ?? null);

            if ($switchAccountId === null || $switchAccountId === $account->switch_account_id) {
                continue;
            }

            $managedDescendant = $this->managedAccount($account, $switchAccountId);

            if ($managedDescendant === null) {
                continue;
            }

            $this->projectParent(
                $managedDescendant,
                $this->string($descendant['parent_id'] ?? null) ?? $this->parentId($descendant),
            );
            $managedDescendant->forceFill([
                'descendants_count' => is_numeric($descendant['descendants_count'] ?? null)
                    ? max(0, (int) $descendant['descendants_count'])
                    : 0,
                'hierarchy_synced_at' => now(),
            ])->save();
        }
    }

    private function projectParent(SwitchAccount $account, ?string $parentSwitchAccountId): void
    {
        $parent = $parentSwitchAccountId === null || $parentSwitchAccountId === $account->switch_account_id
            ? null
            : $this->managedAccount($account, $parentSwitchAccountId);

        $account->forceFill([
            'parent_account_id' => $parent?->getKey(),
            'parent_switch_account_id' => $parentSwitchAccountId,
            'hierarchy_synced_at' => now(),
        ])->save();
    }

    private function managedAccount(SwitchAccount $scope, string $switchAccountId): ?SwitchAccount
    {
        return SwitchAccount::query()
            ->where('organization_id', $scope->organization_id)
            ->where('switch_account_id', $switchAccountId)
            ->first();
    }

    /** @param array<string, mixed> $snapshot */
    private function parentId(array $snapshot): ?string
    {
        $tree = is_array($snapshot['tree'] ?? null) ? $snapshot['tree'] : [];

        for ($index = count($tree) - 1; $index >= 0; $index--) {
            $parentId = $this->string($tree[$index] ?? null);

            if ($parentId !== null) {
                return $parentId;
            }
        }

        return null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
