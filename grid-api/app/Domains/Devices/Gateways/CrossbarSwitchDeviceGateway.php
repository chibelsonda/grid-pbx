<?php

namespace App\Domains\Devices\Gateways;

use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Dto\Devices\DeviceWriteData;
use GridPbx\Switch\Resources\DeviceResourceClient;

class CrossbarSwitchDeviceGateway implements SwitchDeviceGateway
{
    public function __construct(private readonly DeviceResourceClient $devices) {}

    public function create(SwitchAccount $account, array $device): array
    {
        return $this->devices
            ->create($account->switch_account_id, $this->writeData($device))
            ->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $device): array
    {
        return $this->devices
            ->update($account->switch_account_id, $resourceId, $this->writeData($device))
            ->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->devices->delete($account->switch_account_id, $resourceId);
    }

    /** @param array<string, mixed> $device */
    private function writeData(array $device): DeviceWriteData
    {
        return new DeviceWriteData(
            name: $device['name'],
            deviceType: $device['device_type'],
            enabled: $device['is_enabled'],
            ownerId: $device['owner_switch_resource_id'],
            make: $device['make'],
            model: $device['model'],
            macAddress: $device['mac_address'],
            sipUsername: $device['sip_username'],
            sipPassword: $device['sip_password'],
        );
    }
}
