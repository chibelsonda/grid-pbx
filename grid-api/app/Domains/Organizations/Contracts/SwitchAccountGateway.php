<?php

namespace App\Domains\Organizations\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchAccountGateway
{
    /** @return list<array{key: string, label: string, emergency: bool}> */
    public function restrictionClassifiers(SwitchAccount $account): array;

    /** @return array<string, mixed> */
    public function find(SwitchAccount $account): array;

    /** @return list<array<string, mixed>> */
    public function descendants(SwitchAccount $account): array;

    /** @return array<string, mixed> */
    public function findBySwitchAccountId(string $switchAccountId): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateSettings(SwitchAccount $account, array $data): array;

    /** @return array<string, mixed> */
    public function updateEnabled(SwitchAccount $account, bool $enabled): array;
}
