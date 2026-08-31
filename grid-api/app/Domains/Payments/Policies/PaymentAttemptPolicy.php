<?php

namespace App\Domains\Payments\Policies;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\OrganizationAccessService;

class PaymentAttemptPolicy
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function viewAny(User $user, SwitchAccount $account): bool
    {
        return $this->access->canViewServices($user, $account);
    }

    public function view(User $user, SwitchAccount $account): bool
    {
        return $this->viewAny($user, $account);
    }

    public function charge(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageAccountSettings($user, $account);
    }

    public function void(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageAccountSettings($user, $account);
    }

    public function refund(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageAccountSettings($user, $account);
    }

    public function attachPaymentMethod(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageAccountSettings($user, $account);
    }

    public function retryWebhookReconciliation(User $user, SwitchAccount $account): bool
    {
        return $this->access->canManageAccountSettings($user, $account);
    }
}
