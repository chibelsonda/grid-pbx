<?php

namespace App\Domains\Organizations\Services;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\SwitchAccount;

class OrganizationAccessService
{
    public function roleFor(User $user, SwitchAccount $account): ?OrganizationRole
    {
        $role = $user->organizations()
            ->whereKey($account->organization_id)
            ->value('organization_user.role');

        return is_string($role) ? OrganizationRole::tryFrom($role) : null;
    }

    public function canManageDevices(User $user, SwitchAccount $account): bool
    {
        return $this->roleFor($user, $account)?->canManageDevices() ?? false;
    }

    public function canManageExtensions(User $user, SwitchAccount $account): bool
    {
        return $this->roleFor($user, $account)?->canManageDevices() ?? false;
    }

    public function canManageVoicemail(User $user, SwitchAccount $account): bool
    {
        return $this->roleFor($user, $account)?->canManageVoicemail() ?? false;
    }

    public function canManageCallRouting(User $user, SwitchAccount $account): bool
    {
        return $this->roleFor($user, $account)?->canManageCallRouting() ?? false;
    }

    public function canManageMedia(User $user, SwitchAccount $account): bool
    {
        return $this->roleFor($user, $account)?->canManageMedia() ?? false;
    }

    public function canViewCallDetailRecords(User $user, SwitchAccount $account): bool
    {
        return $this->roleFor($user, $account) !== null;
    }

    public function canSyncCallDetailRecords(User $user, SwitchAccount $account): bool
    {
        return $this->roleFor($user, $account)?->canSyncCallDetailRecords() ?? false;
    }

    public function canViewServices(User $user, SwitchAccount $account): bool
    {
        return $this->roleFor($user, $account)?->canViewServices() ?? false;
    }
}
