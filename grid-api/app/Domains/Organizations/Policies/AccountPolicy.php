<?php

namespace App\Domains\Organizations\Policies;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class AccountPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function update(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageAccountSettings($user, $account);
    }

    public function refresh(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageAccountSettings($user, $account);
    }

    public function setStatus(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageAccountSettings($user, $account);
    }
}
