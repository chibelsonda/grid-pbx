<?php

namespace App\Domains\Organizations\Application\Queries;

use App\Domains\IdentityAccess\Infrastructure\Models\User;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use Illuminate\Database\Eloquent\Collection;

class ListAccessibleAccounts
{
    /** @return Collection<int, KazooAccount> */
    public function handle(User $user): Collection
    {
        return KazooAccount::query()
            ->where('is_enabled', true)
            ->whereHas('organization.users', fn ($query) => $query->whereKey($user->getKey()))
            ->with('organization:id,name')
            ->orderBy('name')
            ->get();
    }
}
