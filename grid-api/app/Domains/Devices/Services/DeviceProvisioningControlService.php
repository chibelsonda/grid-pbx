<?php

namespace App\Domains\Devices\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeviceProvisioningControlService
{
    public function __construct(
        private readonly SwitchDeviceGateway $gateway,
        private readonly AuditService $audit,
    ) {}

    public function sync(
        SwitchAccount $account,
        SwitchDevice $device,
        User $actor,
        bool $reboot,
        ?string $ipAddress,
    ): void {
        if (! in_array($device->device_type, ['sip_device', 'fax', 'ata'], true)) {
            throw ValidationException::withMessages([
                'device' => ['Provisioning commands are not supported for this device type.'],
            ]);
        }

        $action = $reboot ? 'device.provisioning_reprovisioned' : 'device.provisioning_synchronized';

        try {
            $this->gateway->sync($account, $device->switch_resource_id, $reboot);
            $this->audit->record($actor, $account, $action, 'succeeded', $device->switch_resource_id, [
                'reboot' => $reboot,
            ], $ipAddress);
        } catch (Throwable $exception) {
            $this->audit->record($actor, $account, $action, 'failed', $device->switch_resource_id, [
                'reboot' => $reboot,
                'error' => $exception->getMessage(),
            ], $ipAddress);

            throw $exception;
        }
    }
}
