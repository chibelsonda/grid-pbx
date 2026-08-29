<?php

namespace App\Domains\Devices\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Enums\DeviceRegistrationStatus;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\LineKeys\Services\LineKeyProjectionService;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnexpectedValueException;

class DeviceMutationService
{
    public function __construct(
        private readonly SwitchDeviceGateway $gateway,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
        private readonly AuditService $audit,
        private readonly LineKeyProjectionService $lineKeyProjection,
        private readonly DeviceMutationDataFactory $mutationData,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(
        SwitchAccount $account,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchDevice {
        try {
            $snapshot = $this->gateway->create($account, $this->mutationData->make($account, $data));

            return DB::transaction(function () use ($account, $actor, $data, $ipAddress, $snapshot): SwitchDevice {
                $device = $this->project($account, $snapshot);
                $this->audit->record(
                    $actor,
                    $account,
                    'device.created',
                    'succeeded',
                    $device->switch_resource_id,
                    $this->safeMetadata($data),
                    $ipAddress,
                );

                return $device;
            });
        } catch (Throwable $exception) {
            $this->recordFailure($actor, $account, 'device.create_failed', null, $exception, $ipAddress);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(
        SwitchAccount $account,
        SwitchDevice $device,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchDevice {
        try {
            $snapshot = $this->gateway->update(
                $account,
                $device->switch_resource_id,
                $this->mutationData->make($account, $data),
            );

            return DB::transaction(function () use ($account, $actor, $data, $ipAddress, $snapshot): SwitchDevice {
                $projected = $this->project($account, $snapshot);
                $this->audit->record(
                    $actor,
                    $account,
                    'device.updated',
                    'succeeded',
                    $projected->switch_resource_id,
                    $this->safeMetadata($data),
                    $ipAddress,
                );

                return $projected;
            });
        } catch (Throwable $exception) {
            $this->recordFailure(
                $actor,
                $account,
                'device.update_failed',
                $device->switch_resource_id,
                $exception,
                $ipAddress,
            );

            throw $exception;
        }
    }

    public function delete(
        SwitchAccount $account,
        SwitchDevice $device,
        User $actor,
        ?string $ipAddress = null,
    ): void {
        try {
            $this->gateway->delete($account, $device->switch_resource_id);
            DB::transaction(function () use ($account, $actor, $device, $ipAddress): void {
                $device->delete();
                $this->audit->record(
                    $actor,
                    $account,
                    'device.deleted',
                    'succeeded',
                    $device->switch_resource_id,
                    ['name' => $device->name],
                    $ipAddress,
                );
            });
        } catch (Throwable $exception) {
            $this->recordFailure(
                $actor,
                $account,
                'device.delete_failed',
                $device->switch_resource_id,
                $exception,
                $ipAddress,
            );

            throw $exception;
        }
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
                ->value('extension_id');
        $device = SwitchDevice::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $resourceId,
        ]);

        if (! $device->exists) {
            $device->registration_status = DeviceRegistrationStatus::Unknown;
            $device->registration_checked_at = null;
        }

        $device->fill([
            'switch_extension_id' => $extensionId,
            'owner_switch_resource_id' => $ownerResourceId,
            'name' => $this->stringValue($snapshot['name'] ?? null),
            'device_type' => $this->stringValue($snapshot['device_type'] ?? null),
            'make' => $this->stringValue($snapshot['make'] ?? Arr::get($snapshot, 'provision.endpoint_brand')),
            'endpoint_family' => $this->stringValue(Arr::get($snapshot, 'provision.endpoint_family')),
            'model' => $this->modelValue($snapshot['model'] ?? Arr::get($snapshot, 'provision.endpoint_model')),
            'mac_address' => $this->stringValue($snapshot['mac_address'] ?? Arr::get($snapshot, 'provision.mac_address')),
            'is_enabled' => (bool) ($snapshot['enabled'] ?? true),
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $device->deleted_at = null;
        $device->save();
        $this->lineKeyProjection->project($device, $snapshot);

        return $device->load('extension:extension_id,id,display_name,extension');
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function modelValue(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return $this->stringValue($value[0] ?? null);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function safeMetadata(array $data): array
    {
        return [
            'name' => $data['name'],
            'device_type' => $data['device_type'],
            'assigned_extension_id' => $data['assigned_extension_id'] ?? null,
            'is_enabled' => $data['is_enabled'],
            'credentials_changed' => isset($data['sip_username'])
                || isset($data['sip_password'])
                || Arr::has($data, 'sip.username')
                || Arr::has($data, 'sip.password'),
        ];
    }

    private function recordFailure(
        User $actor,
        SwitchAccount $account,
        string $action,
        ?string $resourceId,
        Throwable $exception,
        ?string $ipAddress,
    ): void {
        $this->audit->record(
            $actor,
            $account,
            $action,
            'failed',
            $resourceId,
            ['error_type' => $exception::class],
            $ipAddress,
        );
    }
}
