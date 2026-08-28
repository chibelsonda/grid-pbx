<?php

namespace App\Domains\CallRouting\Policies;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class CallflowPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function update(User $user, SwitchCallflow $callflow, SwitchAccount $account): bool
    {
        return $callflow->switch_account_id === $account->getKey()
            && $this->access->canManageCallRouting($user, $account);
    }

    public function create(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageCallRouting($user, $account);
    }

    public function delete(User $user, SwitchCallflow $callflow, SwitchAccount $account): bool
    {
        return $callflow->switch_account_id === $account->getKey()
            && $this->access->canManageCallRouting($user, $account);
    }
}
