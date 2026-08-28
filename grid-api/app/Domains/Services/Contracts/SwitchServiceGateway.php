<?php

namespace App\Domains\Services\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchServiceGateway
{
    /** @return array<string, mixed> */
    public function snapshot(SwitchAccount $account): array;
}
