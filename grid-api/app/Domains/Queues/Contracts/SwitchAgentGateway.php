<?php

namespace App\Domains\Queues\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchAgentGateway
{
    /** @return array<string, mixed> */
    public function status(SwitchAccount $account, string $switchUserId): array;

    public function updateStatus(SwitchAccount $account, string $switchUserId, string $status, ?int $pauseTimeout): void;
}
