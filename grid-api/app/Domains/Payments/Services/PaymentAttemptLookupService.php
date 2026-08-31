<?php

namespace App\Domains\Payments\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Models\PaymentAttempt;

final class PaymentAttemptLookupService
{
    public function findForAccount(SwitchAccount $account, string $publicId): PaymentAttempt
    {
        return PaymentAttempt::query()
            ->where('switch_account_id', $account->getKey())
            ->where('id', $publicId)
            ->firstOrFail();
    }
}
