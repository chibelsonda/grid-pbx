<?php

namespace App\Domains\Services\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Models\SwitchServiceSummary;

class ServiceOverviewService
{
    public function get(SwitchAccount $account): ?SwitchServiceSummary
    {
        return $account->serviceSummary()->with([
            'billingResellerAccount:account_id,id,name,realm',
            'plans',
            'quantities',
            'switchAccount.serviceLimit',
            'switchAccount.billingSummary',
            'switchAccount.ledgerSummaries' => fn ($query) => $query
                ->orderBy('source_service'),
            'switchAccount.billingTransactions' => fn ($query) => $query
                ->orderByDesc('switch_created_at')
                ->orderByDesc('billing_transaction_id')
                ->limit(50),
        ])->first();
    }
}
