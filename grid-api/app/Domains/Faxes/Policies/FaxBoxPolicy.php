<?php

namespace App\Domains\Faxes\Policies;

use App\Domains\Faxes\Models\SwitchFaxBox;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class FaxBoxPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}
    public function viewAny(User $user, SwitchAccount $account): bool { return $this->access->roleFor($user, $account) !== null; }
    public function view(User $user, SwitchFaxBox $box, SwitchAccount $account): bool { return $box->switch_account_id === $account->getKey() && $this->viewAny($user, $account); }
    public function create(User $user, SwitchAccount $account): bool { return $this->access->canManageCallRouting($user, $account); }
    public function update(User $user, SwitchFaxBox $box, SwitchAccount $account): bool { return $box->switch_account_id === $account->getKey() && $this->create($user, $account); }
    public function delete(User $user, SwitchFaxBox $box, SwitchAccount $account): bool { return $this->update($user, $box, $account); }
    public function sync(User $user, SwitchAccount $account): bool { return $this->create($user, $account); }
}
