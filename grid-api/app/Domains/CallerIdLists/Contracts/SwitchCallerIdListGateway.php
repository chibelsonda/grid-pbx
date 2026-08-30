<?php

namespace App\Domains\CallerIdLists\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use Generator;

interface SwitchCallerIdListGateway
{
    /**
     * @return Generator<int, array{list: array<string, mixed>, entries: list<array<string, mixed>>}>
     */
    public function all(SwitchAccount $account): Generator;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(SwitchAccount $account, array $data): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function update(SwitchAccount $account, string $resourceId, array $data): array;

    public function delete(SwitchAccount $account, string $resourceId): void;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createEntry(SwitchAccount $account, string $listResourceId, array $data): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function updateEntry(SwitchAccount $account, string $listResourceId, string $entryResourceId, array $data): array;

    public function deleteEntry(SwitchAccount $account, string $listResourceId, string $entryResourceId): void;

    /** @return array{list: array<string, mixed>, entries: list<array<string, mixed>>} */
    public function details(SwitchAccount $account, string $resourceId): array;
}
