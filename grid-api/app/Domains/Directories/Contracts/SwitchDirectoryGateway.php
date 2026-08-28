<?php

namespace App\Domains\Directories\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use Generator;

interface SwitchDirectoryGateway
{
    /** @return Generator<int, array<string, mixed>> */
    public function all(SwitchAccount $account): Generator;

    /** @return array<string, mixed> */
    public function get(SwitchAccount $account, string $resourceId): array;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(SwitchAccount $account, array $data): array;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(SwitchAccount $account, string $resourceId, array $data): array;

    /** @param array<string, string> $members
     * @return array<string, mixed>
     */
    public function replaceMembers(SwitchAccount $account, string $resourceId, array $members): array;

    public function delete(SwitchAccount $account, string $resourceId): void;
}
