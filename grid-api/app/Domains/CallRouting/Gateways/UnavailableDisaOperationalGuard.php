<?php

namespace App\Domains\CallRouting\Gateways;

use App\Domains\CallRouting\Contracts\DisaOperationalGuard;
use App\Domains\CallRouting\Dto\DisaOperationalReadiness;
use App\Domains\Organizations\Models\SwitchAccount;

/**
 * Safe default until the client's carrier/SBC enforcement contract is known.
 */
final class UnavailableDisaOperationalGuard implements DisaOperationalGuard
{
    public function inspect(SwitchAccount $account): DisaOperationalReadiness
    {
        return DisaOperationalReadiness::unavailable();
    }
}
