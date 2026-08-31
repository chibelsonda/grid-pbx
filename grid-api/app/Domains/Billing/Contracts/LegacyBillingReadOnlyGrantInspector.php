<?php

namespace App\Domains\Billing\Contracts;

use Illuminate\Database\ConnectionInterface;

interface LegacyBillingReadOnlyGrantInspector
{
    public function isReadOnly(ConnectionInterface $connection): bool;
}
