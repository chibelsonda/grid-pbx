<?php

namespace App\Domains\Voicemail\Policies;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;

class VoicemailBoxPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->roleFor($user, $account) !== null;
    }

    public function create(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageVoicemail($user, $account);
    }

    public function viewMessages(User $user, SwitchVoicemailBox $voicemailBox, SwitchAccount $account): bool
    {
        return $voicemailBox->switch_account_id === $account->getKey()
            && $this->access->roleFor($user, $account) !== null;
    }

    public function update(User $user, SwitchVoicemailBox $voicemailBox, SwitchAccount $account): bool
    {
        return $voicemailBox->switch_account_id === $account->getKey()
            && $this->access->canManageVoicemail($user, $account);
    }

    public function delete(User $user, SwitchVoicemailBox $voicemailBox, SwitchAccount $account): bool
    {
        return $voicemailBox->switch_account_id === $account->getKey()
            && $this->access->canManageVoicemail($user, $account);
    }
}
