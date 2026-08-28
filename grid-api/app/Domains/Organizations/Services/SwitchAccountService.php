<?php

namespace App\Domains\Organizations\Services;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Eloquent\Collection;

class SwitchAccountService
{
    /** @return Collection<int, SwitchAccount> */
    public function accessibleTo(User $user): Collection
    {
        $accounts = SwitchAccount::query()
            ->where('is_enabled', true)
            ->whereHas('organization.users', fn ($query) => $query->whereKey($user->getKey()))
            ->with('organization:organization_id,id,name')
            ->orderBy('name')
            ->get();
        $rolesByOrganization = $user->organizations()
            ->pluck('organization_user.role', 'organizations.organization_id');

        $accounts->each(function (SwitchAccount $account) use ($rolesByOrganization): void {
            $account->setAttribute(
                'organization_role',
                $rolesByOrganization->get($account->organization_id),
            );
        });

        return $accounts;
    }

    public function findAccessible(User $user, string $accountId): SwitchAccount
    {
        return SwitchAccount::query()
            ->where('id', $accountId)
            ->where('is_enabled', true)
            ->whereHas('organization.users', fn ($query) => $query->whereKey($user->getKey()))
            ->firstOrFail();
    }
}
