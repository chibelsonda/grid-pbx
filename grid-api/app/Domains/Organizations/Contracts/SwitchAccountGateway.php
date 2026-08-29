<?php

namespace App\Domains\Organizations\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchAccountGateway
{
    /** @return array<string, mixed> */
    public function find(SwitchAccount $account): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateSettings(SwitchAccount $account, array $data): array;

    /** @return array<string, mixed> */
    public function updateEnabled(SwitchAccount $account, bool $enabled): array;
}
