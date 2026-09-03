<?php

namespace App\Domains\CallRouting\Contracts;

use App\Domains\CallRouting\Dto\DisaOperationalReadiness;
use App\Domains\Organizations\Models\SwitchAccount;

/**
 * Reports controls enforced in the live ingress path before Switch reaches DISA.
 *
 * Implementations must derive this status from the carrier/SBC guard itself. A
 * stored administrator assertion is not sufficient because deployed Callflows
 * continue to execute independently of GridPBX.
 */
interface DisaOperationalGuard
{
    public function inspect(SwitchAccount $account): DisaOperationalReadiness;
}
