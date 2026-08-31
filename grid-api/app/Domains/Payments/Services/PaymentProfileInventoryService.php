<?php

namespace App\Domains\Payments\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Models\PaymentCustomerProfile;
use Illuminate\Database\Eloquent\Collection;

final class PaymentProfileInventoryService
{
    /** @return Collection<int, PaymentCustomerProfile> */
    public function forAccount(SwitchAccount $account, int $limit = 50): Collection
    {
        return PaymentCustomerProfile::query()
            ->where('switch_account_id', $account->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('payment_customer_profile_id')
            ->limit(max(1, min(50, $limit)))
            ->get();
    }
}
