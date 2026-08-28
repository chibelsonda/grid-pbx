<?php

namespace App\Domains\Groups\Policies;

use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class GroupPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->roleFor($user, $account) !== null;
    }

    public function view(User $user, SwitchGroup $group, SwitchAccount $account): bool
    {
        return $group->switch_account_id === $account->getKey() && $this->viewAny($user, $account);
    }

    public function create(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageCallRouting($user, $account);
    }

    public function update(User $user, SwitchGroup $group, SwitchAccount $account): bool
    {
        return $group->switch_account_id === $account->getKey() && $this->create($user, $account);
    }

    public function delete(User $user, SwitchGroup $group, SwitchAccount $account): bool
    {
        return $this->update($user, $group, $account);
    }

    public function sync(User $user, SwitchAccount $account): bool
    {
        return $this->create($user, $account);
    }
}
