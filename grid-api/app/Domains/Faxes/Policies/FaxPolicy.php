<?php

namespace App\Domains\Faxes\Policies;

use App\Domains\Faxes\Models\SwitchFax;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class FaxPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}
    public function viewAny(User $user, SwitchAccount $account): bool { return $this->access->roleFor($user, $account) !== null; }
    public function view(User $user, SwitchFax $fax, SwitchAccount $account): bool { return $fax->switch_account_id === $account->getKey() && $this->viewAny($user, $account); }
}
