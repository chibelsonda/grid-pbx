<?php

namespace App\Domains\Queues\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchAgentGateway
{
    /** @return list<array{agent_id: string, status: string, timestamp: int}> */
    public function availability(SwitchAccount $account): array;

    /** @return list<string> */
    public function queueIds(SwitchAccount $account, string $switchUserId): array;

    /** @return list<array{agent_id: string, total_calls: int, answered_calls: int, missed_calls: int}> */
    public function statistics(SwitchAccount $account): array;

    /** @return array<string, mixed> */
    public function status(SwitchAccount $account, string $switchUserId): array;

    public function updateStatus(SwitchAccount $account, string $switchUserId, string $status, ?int $pauseTimeout): void;

    /** @return list<string> */
    public function updateQueueMembership(SwitchAccount $account, string $switchUserId, string $action, string $switchQueueId): array;
}
