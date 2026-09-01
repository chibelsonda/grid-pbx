<?php

namespace App\Domains\Queues\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use Generator;

interface SwitchQueueGateway
{
    /** @return array{configuration_available: bool, live_agent_controls_available: bool, agent_statistics_available: bool, statistics_available: bool} */
    public function capabilities(SwitchAccount $account): array;

    /** @return array{current_timestamp: int, statistics: list<array{queue_id: string, status: string, entered_timestamp: int|null, wait_time: int|null, talk_time: int|null}>} */
    public function statistics(SwitchAccount $account): array;

    /** @return Generator<int, array<string, mixed>> */
    public function all(SwitchAccount $account): Generator;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(SwitchAccount $account, array $data): array;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(SwitchAccount $account, string $resourceId, array $data): array;

    /** @param list<string> $switchUserIds
     * @return array<string, mixed>
     */
    public function replaceRoster(SwitchAccount $account, string $resourceId, array $switchUserIds): array;

    public function delete(SwitchAccount $account, string $resourceId): void;
}
