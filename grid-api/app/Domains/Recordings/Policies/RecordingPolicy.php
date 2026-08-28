<?php

namespace App\Domains\Recordings\Policies;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;
use App\Domains\Recordings\Models\SwitchRecording;

class RecordingPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}
    public function viewAny(User $user, SwitchAccount $account): bool { return $this->access->canViewCallDetailRecords($user, $account); }
    public function view(User $user, SwitchRecording $recording, SwitchAccount $account): bool { return $recording->switch_account_id === $account->getKey() && $this->viewAny($user, $account); }
    public function sync(User $user, SwitchAccount $account): bool { return $this->access->canSyncCallDetailRecords($user, $account); }
}
