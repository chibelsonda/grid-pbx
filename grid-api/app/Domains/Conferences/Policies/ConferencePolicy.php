<?php

namespace App\Domains\Conferences\Policies;

use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class ConferencePolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->roleFor($user, $account) !== null;
    }

    public function view(User $user, SwitchConference $conference, SwitchAccount $account): bool
    {
        return $conference->switch_account_id === $account->getKey() && $this->viewAny($user, $account);
    }

    public function create(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageCallRouting($user, $account);
    }

    public function update(User $user, SwitchConference $conference, SwitchAccount $account): bool
    {
        return $conference->switch_account_id === $account->getKey() && $this->create($user, $account);
    }

    public function delete(User $user, SwitchConference $conference, SwitchAccount $account): bool
    {
        return $this->update($user, $conference, $account);
    }

    public function control(User $user, SwitchConference $conference, SwitchAccount $account): bool
    {
        return $this->update($user, $conference, $account);
    }

    public function sync(User $user, SwitchAccount $account): bool
    {
        return $this->create($user, $account);
    }
}
