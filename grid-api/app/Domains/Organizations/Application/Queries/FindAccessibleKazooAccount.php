<?php

namespace App\Domains\Organizations\Application\Queries;

use App\Domains\IdentityAccess\Infrastructure\Models\User;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;

class FindAccessibleKazooAccount
{
    public function handle(User $user, string $accountId): KazooAccount
    {
        return KazooAccount::query()
            ->whereKey($accountId)
            ->where('is_enabled', true)
            ->whereHas('organization.users', fn ($query) => $query->whereKey($user->getKey()))
            ->firstOrFail();
    }
}
