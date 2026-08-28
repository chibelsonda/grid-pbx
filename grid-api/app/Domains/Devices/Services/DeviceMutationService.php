<?php

namespace App\Domains\Devices\Services;

use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Illuminate\Support\Arr;
use UnexpectedValueException;

class DeviceMutationService
{
    public function __construct(
        private readonly SwitchDeviceGateway $gateway,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(SwitchAccount $account, array $data): SwitchDevice
    {
        return $this->project(
            $account,
            $this->gateway->create($account, $this->mutationData($account, $data)),
        );
    }

    /** @param array<string, mixed> $data */
    public function update(SwitchAccount $account, SwitchDevice $device, array $data): SwitchDevice
    {
        return $this->project(
            $account,
            $this->gateway->update(
                $account,
                $device->switch_resource_id,
                $this->mutationData($account, $data),
            ),
        );
    }

    public function delete(SwitchAccount $account, SwitchDevice $device): void
    {
        $this->gateway->delete($account, $device->switch_resource_id);
        $device->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mutationData(SwitchAccount $account, array $data): array
    {
        $extension = isset($data['assigned_extension_id'])
            ? $account->extensions()->whereKey($data['assigned_extension_id'])->firstOrFail()
            : null;

        return [
            'name' => $data['name'],
            'device_type' => $data['device_type'],
            'is_enabled' => $data['is_enabled'],
            'owner_switch_resource_id' => $extension?->switch_resource_id,
            'make' => $data['make'] ?? null,
            'model' => $data['model'] ?? null,
            'mac_address' => $data['mac_address'] ?? null,
            'sip_username' => $data['sip_username'] ?? null,
            'sip_password' => $data['sip_password'] ?? null,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function project(SwitchAccount $account, array $snapshot): SwitchDevice
    {
        $resourceId = $this->stringValue($snapshot['id'] ?? null);

        if ($resourceId === null) {
            throw new UnexpectedValueException('Switch device response is missing its resource identifier.');
        }

        $ownerResourceId = $this->stringValue($snapshot['owner_id'] ?? null);
        $extensionId = $ownerResourceId === null
            ? null
            : $account->extensions()
                ->where('switch_resource_id', $ownerResourceId)
                ->value('id');
        $device = SwitchDevice::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $resourceId,
        ]);
        $device->fill([
            'switch_extension_id' => $extensionId,
            'owner_switch_resource_id' => $ownerResourceId,
            'name' => $this->stringValue($snapshot['name'] ?? null),
            'device_type' => $this->stringValue($snapshot['device_type'] ?? null),
            'make' => $this->stringValue($snapshot['make'] ?? Arr::get($snapshot, 'provision.endpoint_brand')),
            'model' => $this->stringValue($snapshot['model'] ?? Arr::get($snapshot, 'provision.endpoint_model')),
            'mac_address' => $this->stringValue($snapshot['mac_address'] ?? Arr::get($snapshot, 'provision.mac_address')),
            'is_enabled' => (bool) ($snapshot['enabled'] ?? true),
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
            'source_payload' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $device->deleted_at = null;
        $device->save();

        return $device->load('extension:id,display_name,extension');
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
