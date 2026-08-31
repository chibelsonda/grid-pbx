<?php

namespace App\Domains\PhoneNumbers\Policies;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;

class PhoneNumberPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->roleFor($user, $account) !== null;
    }

    public function view(User $user, SwitchPhoneNumber $phoneNumber, SwitchAccount $account): bool
    {
        return $phoneNumber->switch_account_id === $account->getKey()
            && $this->viewAny($user, $account);
    }
}
