<?php

namespace App\Domains\TemporalRouting\Policies;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;

class TemporalRuleSetPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->roleFor($user, $account) !== null;
    }

    public function view(User $user, SwitchTemporalRuleSet $set, SwitchAccount $account): bool
    {
        return $set->switch_account_id === $account->getKey() && $this->viewAny($user, $account);
    }

    public function create(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageCallRouting($user, $account);
    }

    public function update(User $user, SwitchTemporalRuleSet $set, SwitchAccount $account): bool
    {
        return $set->switch_account_id === $account->getKey() && $this->create($user, $account);
    }

    public function delete(User $user, SwitchTemporalRuleSet $set, SwitchAccount $account): bool
    {
        return $this->update($user, $set, $account);
    }

    public function sync(User $user, SwitchAccount $account): bool
    {
        return $this->create($user, $account);
    }
}
