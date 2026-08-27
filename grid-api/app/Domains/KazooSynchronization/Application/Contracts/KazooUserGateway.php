<?php

namespace App\Domains\KazooSynchronization\Application\Contracts;

use App\Domains\Organizations\Infrastructure\Models\KazooAccount;

interface KazooUserGateway
{
    /** @return iterable<int, array<string, mixed>> */
    public function users(KazooAccount $account): iterable;
}
