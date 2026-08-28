<?php

namespace App\Domains\CallDetailRecords\Policies;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class CallDetailRecordPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->canViewCallDetailRecords($user, $account);
    }

    public function view(
        User $user,
        SwitchCallDetailRecord $record,
        SwitchAccount $account,
    ): bool {
        return $record->switch_account_id === $account->getKey()
            && $this->access->canViewCallDetailRecords($user, $account);
    }

    public function sync(User $user, SwitchAccount $account): bool
    {
        return $this->access->canSyncCallDetailRecords($user, $account);
    }
}
