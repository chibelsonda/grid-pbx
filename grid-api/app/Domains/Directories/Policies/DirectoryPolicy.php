<?php

namespace App\Domains\Directories\Policies;

use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class DirectoryPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->roleFor($user, $account) !== null;
    }

    public function view(User $user, SwitchDirectory $directory, SwitchAccount $account): bool
    {
        return $directory->switch_account_id === $account->getKey() && $this->viewAny($user, $account);
    }

    public function create(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageCallRouting($user, $account);
    }

    public function update(User $user, SwitchDirectory $directory, SwitchAccount $account): bool
    {
        return $directory->switch_account_id === $account->getKey() && $this->create($user, $account);
    }

    public function delete(User $user, SwitchDirectory $directory, SwitchAccount $account): bool
    {
        return $this->update($user, $directory, $account);
    }

    public function sync(User $user, SwitchAccount $account): bool
    {
        return $this->create($user, $account);
    }
}
