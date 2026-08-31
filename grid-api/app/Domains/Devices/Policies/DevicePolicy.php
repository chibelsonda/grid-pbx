<?php

namespace App\Domains\Devices\Policies;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class DevicePolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->roleFor($user, $account) !== null;
    }

    public function create(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageDevices($user, $account);
    }

    public function update(User $user, SwitchDevice $device, SwitchAccount $account): bool
    {
        return $device->switch_account_id === $account->getKey()
            && $this->access->canManageDevices($user, $account);
    }

    public function delete(User $user, SwitchDevice $device, SwitchAccount $account): bool
    {
        return $device->switch_account_id === $account->getKey()
            && $this->access->canManageDevices($user, $account);
    }
}
