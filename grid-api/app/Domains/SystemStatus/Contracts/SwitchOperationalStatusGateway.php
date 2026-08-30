<?php

namespace App\Domains\SystemStatus\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchOperationalStatusGateway
{
    /** @return array{presence_subscription_diagnostics_available: bool, parked_call_summary_available: bool, active_parked_call_count: int|null} */
    public function inspect(SwitchAccount $account): array;
}
