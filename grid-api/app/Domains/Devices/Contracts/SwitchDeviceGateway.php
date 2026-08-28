<?php

namespace App\Domains\Devices\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchDeviceGateway
{
    /** @return list<array{key: string, label: string, emergency: bool}> */
    public function restrictionClassifiers(SwitchAccount $account): array;

    /**
     * @param  array<string, mixed>  $device
     * @return array<string, mixed>
     */
    public function create(SwitchAccount $account, array $device): array;

    /**
     * @param  array<string, mixed>  $device
     * @return array<string, mixed>
     */
    public function update(SwitchAccount $account, string $resourceId, array $device): array;

    public function delete(SwitchAccount $account, string $resourceId): void;
}
