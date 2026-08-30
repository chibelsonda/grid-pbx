<?php

namespace App\Domains\CallerIdLists\Policies;

use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class CallerIdListPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->roleFor($user, $account) !== null;
    }

    public function sync(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageCallRouting($user, $account);
    }

    public function view(User $user, SwitchCallerIdList $list, SwitchAccount $account): bool
    {
        return $list->switch_account_id === $account->getKey() && $this->viewAny($user, $account);
    }

    public function create(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageCallRouting($user, $account);
    }

    public function update(User $user, SwitchCallerIdList $list, SwitchAccount $account): bool
    {
        return $list->switch_account_id === $account->getKey() && $this->create($user, $account);
    }

    public function delete(User $user, SwitchCallerIdList $list, SwitchAccount $account): bool
    {
        return $this->update($user, $list, $account);
    }
}
