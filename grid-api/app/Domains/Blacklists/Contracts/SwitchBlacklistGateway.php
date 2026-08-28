<?php

namespace App\Domains\Blacklists\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use Generator;

interface SwitchBlacklistGateway
{
    /** @return Generator<int, array<string, mixed>> */ public function all(SwitchAccount $account): Generator;
    /** @return list<string> */ public function activeIds(SwitchAccount $account): array;
    /** @param array<string, mixed> $data @return array<string, mixed> */ public function create(SwitchAccount $account, array $data): array;
    /** @param array<string, mixed> $data @return array<string, mixed> */ public function update(SwitchAccount $account, string $resourceId, array $data): array;
    /** @param list<string> $resourceIds */ public function setActiveIds(SwitchAccount $account, array $resourceIds): void;
    public function delete(SwitchAccount $account, string $resourceId): void;
}
