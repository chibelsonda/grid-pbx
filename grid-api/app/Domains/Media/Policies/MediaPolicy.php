<?php

namespace App\Domains\Media\Policies;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class MediaPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->roleFor($user, $account) !== null;
    }

    public function view(User $user, SwitchMedia $media, SwitchAccount $account): bool
    {
        return $media->switch_account_id === $account->getKey()
            && $this->access->roleFor($user, $account) !== null;
    }

    public function create(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageMedia($user, $account);
    }

    public function update(User $user, SwitchMedia $media, SwitchAccount $account): bool
    {
        return $media->switch_account_id === $account->getKey()
            && $this->access->canManageMedia($user, $account);
    }

    public function delete(User $user, SwitchMedia $media, SwitchAccount $account): bool
    {
        return $this->update($user, $media, $account);
    }

    public function sync(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageMedia($user, $account);
    }

    public function updateMusicOnHold(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageMedia($user, $account);
    }
}
