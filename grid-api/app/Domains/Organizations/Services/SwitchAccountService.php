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
        return SwitchAccount::query()
            ->where('is_enabled', true)
            ->whereHas('organization.users', fn ($query) => $query->whereKey($user->getKey()))
            ->with('organization:id,name')
            ->orderBy('name')
            ->get();
    }

    public function findAccessible(User $user, string $accountId): SwitchAccount
    {
        return SwitchAccount::query()
            ->whereKey($accountId)
            ->where('is_enabled', true)
            ->whereHas('organization.users', fn ($query) => $query->whereKey($user->getKey()))
            ->firstOrFail();
    }
}
