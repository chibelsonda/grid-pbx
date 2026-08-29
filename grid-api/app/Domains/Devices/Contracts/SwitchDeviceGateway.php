<?php

namespace App\Domains\Devices\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchDeviceGateway
{
    /** @return array<string, mixed> */
    public function schemaCompatibility(): array;

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

    public function sync(SwitchAccount $account, string $resourceId, bool $reboot): void;

    /** @return array<string, mixed> */
    public function addHotdeskUser(SwitchAccount $account, string $resourceId, string $userResourceId): array;

    /** @return array<string, mixed> */
    public function removeHotdeskUser(SwitchAccount $account, string $resourceId, string $userResourceId): array;
}
