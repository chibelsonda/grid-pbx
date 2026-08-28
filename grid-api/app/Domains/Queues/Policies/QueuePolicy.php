<?php

namespace App\Domains\Queues\Policies;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;
use App\Domains\Queues\Models\SwitchQueue;

class QueuePolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->roleFor($user, $account) !== null;
    }

    public function view(User $user, SwitchQueue $queue, SwitchAccount $account): bool
    {
        return $queue->switch_account_id === $account->getKey() && $this->viewAny($user, $account);
    }

    public function create(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageCallRouting($user, $account);
    }

    public function update(User $user, SwitchQueue $queue, SwitchAccount $account): bool
    {
        return $queue->switch_account_id === $account->getKey() && $this->create($user, $account);
    }

    public function delete(User $user, SwitchQueue $queue, SwitchAccount $account): bool
    {
        return $this->update($user, $queue, $account);
    }

    public function sync(User $user, SwitchAccount $account): bool
    {
        return $this->create($user, $account);
    }
}
