<?php

namespace App\Domains\Extensions\Policies;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class ExtensionPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->roleFor($user, $account) !== null;
    }

    public function create(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageExtensions($user, $account);
    }

    public function update(User $user, SwitchExtension $extension, SwitchAccount $account): bool
    {
        return $extension->switch_account_id === $account->getKey()
            && $this->access->canManageExtensions($user, $account);
    }

    public function delete(User $user, SwitchExtension $extension, SwitchAccount $account): bool
    {
        return $this->update($user, $extension, $account);
    }
}
