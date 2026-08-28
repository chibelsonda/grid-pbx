<?php

namespace App\Domains\Services\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Models\SwitchServiceSummary;

class ServiceOverviewService
{
    public function get(SwitchAccount $account): ?SwitchServiceSummary
    {
        return $account->serviceSummary()->with(['plans', 'quantities', 'switchAccount.serviceLimit'])->first();
    }
}
