<?php

namespace App\Domains\Voicemail\Policies;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;

class VoicemailMessagePolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function view(
        User $user,
        SwitchVoicemailMessage $message,
        SwitchVoicemailBox $voicemailBox,
        SwitchAccount $account,
    ): bool {
        return $message->switch_account_id === $account->getKey()
            && $message->switch_voicemail_box_id === $voicemailBox->getKey()
            && $this->access->roleFor($user, $account) !== null;
    }

    public function update(
        User $user,
        SwitchVoicemailMessage $message,
        SwitchVoicemailBox $voicemailBox,
        SwitchAccount $account,
    ): bool {
        return $message->switch_account_id === $account->getKey()
            && $message->switch_voicemail_box_id === $voicemailBox->getKey()
            && $this->access->canManageVoicemail($user, $account);
    }
}
