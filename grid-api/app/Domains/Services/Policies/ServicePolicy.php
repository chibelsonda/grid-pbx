<?php

namespace App\Domains\Services\Policies;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class ServicePolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->canViewServices($user, $account);
    }

    public function sync(User $user, SwitchAccount $account): bool
    {
        return $this->viewAny($user, $account);
    }
}
