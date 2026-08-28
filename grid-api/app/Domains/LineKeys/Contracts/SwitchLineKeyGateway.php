<?php

namespace App\Domains\LineKeys\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchLineKeyGateway
{
    /**
     * @param  list<array{category: string, position: int, type: string, value: string|int|null, label: string|null}>  $keys
     * @return array<string, mixed>
     */
    public function update(SwitchAccount $account, string $deviceResourceId, array $keys): array;
}
