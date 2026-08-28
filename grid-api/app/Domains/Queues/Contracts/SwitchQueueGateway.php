<?php

namespace App\Domains\Queues\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use Generator;

interface SwitchQueueGateway
{
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
