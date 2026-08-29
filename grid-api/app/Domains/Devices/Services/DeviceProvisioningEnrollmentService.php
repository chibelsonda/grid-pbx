<?php

namespace App\Domains\Devices\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Devices\Contracts\ManufacturerProvisioningEnrollmentGateway;
use App\Domains\Devices\Enums\ProvisioningEnrollmentStatus;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class DeviceProvisioningEnrollmentService
{
    private const ADAPTER_UNAVAILABLE = 'Manufacturer provisioning enrollment is disabled until the client provider contract and access configuration are available.';

    public function __construct(
        private readonly ProvisioningModelCapabilitiesService $modelCapabilities,
        private readonly ManufacturerProvisioningEnrollmentGateway $gateway,
        private readonly AuditService $audit,
    ) {}

    /** @return array{status: string, provider: string|null, eligible: bool, adapter_available: bool, can_enroll: bool, can_detach: bool, reason: string|null, enrolled_at: string|null, detached_at: string|null} */
    public function status(SwitchDevice $device): array
    {
        $capabilities = $this->modelCapabilities->forDevice($device);
        $catalogProvider = $capabilities['manufacturer_provider'];
        $status = $device->provisioning_enrollment_status
            ?? ProvisioningEnrollmentStatus::NotEnrolled;
        $enrolled = $status === ProvisioningEnrollmentStatus::Enrolled;
        $provider = $enrolled
            ? $device->provisioning_enrollment_provider
            : $catalogProvider;
        $eligible = in_array($device->device_type, ['sip_device', 'fax', 'ata'], true)
            && $device->mac_address !== null
            && $capabilities['matched']
            && $catalogProvider !== null;
        $adapterAvailable = $provider !== null && $this->gateway->supports($provider);

        return [
            'status' => $status->value,
            'provider' => $provider,
            'eligible' => $eligible,
            'adapter_available' => $adapterAvailable,
            'can_enroll' => ! $enrolled && $eligible && $adapterAvailable,
            'can_detach' => $enrolled && $provider !== null && $adapterAvailable,
            'reason' => $this->reason($device, $capabilities['matched'], $catalogProvider, $adapterAvailable),
            'enrolled_at' => $device->provisioning_enrolled_at?->toIso8601String(),
            'detached_at' => $device->provisioning_detached_at?->toIso8601String(),
        ];
    }

    /** @return array{status: string, provider: string|null, eligible: bool, adapter_available: bool, can_enroll: bool, can_detach: bool, reason: string|null, enrolled_at: string|null, detached_at: string|null} */
    public function enroll(
        SwitchAccount $account,
        SwitchDevice $device,
        User $actor,
        ?string $ipAddress,
    ): array {
        if ($device->provisioning_enrollment_status === ProvisioningEnrollmentStatus::Enrolled) {
            throw new ConflictHttpException('This device is already enrolled for manufacturer provisioning.');
        }

        $state = $this->status($device);

        if (! $state['eligible']) {
            throw new ConflictHttpException($state['reason'] ?? 'This device is not eligible for manufacturer provisioning enrollment.');
        }

        if (! $state['adapter_available'] || $state['provider'] === null) {
            throw new ConflictHttpException(self::ADAPTER_UNAVAILABLE);
        }

        $provider = $state['provider'];

        try {
            $this->gateway->enroll($account, $device, $provider);

            DB::transaction(function () use ($account, $device, $actor, $provider, $ipAddress): void {
                $device->forceFill([
                    'provisioning_enrollment_status' => ProvisioningEnrollmentStatus::Enrolled,
                    'provisioning_enrollment_provider' => $provider,
                    'provisioning_enrolled_at' => now(),
                    'provisioning_detached_at' => null,
                ])->save();
                $this->audit->record(
                    $actor,
                    $account,
                    'device.provisioning_enrolled',
                    'succeeded',
                    $device->switch_resource_id,
                    ['provider' => $provider],
                    $ipAddress,
                );
            });
        } catch (Throwable $exception) {
            $this->recordFailure($actor, $account, $device, 'device.provisioning_enroll_failed', $provider, $exception, $ipAddress);

            throw $exception;
        }

        return $this->status($device->refresh());
    }

    /** @return array{status: string, provider: string|null, eligible: bool, adapter_available: bool, can_enroll: bool, can_detach: bool, reason: string|null, enrolled_at: string|null, detached_at: string|null} */
    public function detach(
        SwitchAccount $account,
        SwitchDevice $device,
        User $actor,
        ?string $ipAddress,
    ): array {
        if ($device->provisioning_enrollment_status !== ProvisioningEnrollmentStatus::Enrolled) {
            throw new ConflictHttpException('This device is not enrolled for manufacturer provisioning.');
        }

        $provider = $device->provisioning_enrollment_provider;

        if ($provider === null || ! $this->gateway->supports($provider)) {
            throw new ConflictHttpException(self::ADAPTER_UNAVAILABLE);
        }

        try {
            $this->gateway->detach($account, $device, $provider);

            DB::transaction(function () use ($account, $device, $actor, $provider, $ipAddress): void {
                $device->forceFill([
                    'provisioning_enrollment_status' => ProvisioningEnrollmentStatus::NotEnrolled,
                    'provisioning_enrollment_provider' => null,
                    'provisioning_enrolled_at' => null,
                    'provisioning_detached_at' => now(),
                ])->save();
                $this->audit->record(
                    $actor,
                    $account,
                    'device.provisioning_detached',
                    'succeeded',
                    $device->switch_resource_id,
                    ['provider' => $provider],
                    $ipAddress,
                );
            });
        } catch (Throwable $exception) {
            $this->recordFailure($actor, $account, $device, 'device.provisioning_detach_failed', $provider, $exception, $ipAddress);

            throw $exception;
        }

        return $this->status($device->refresh());
    }

    private function reason(
        SwitchDevice $device,
        bool $modelMatched,
        ?string $catalogProvider,
        bool $adapterAvailable,
    ): ?string {
        if (! in_array($device->device_type, ['sip_device', 'fax', 'ata'], true)) {
            return 'Manufacturer provisioning is not supported for this device type.';
        }

        if ($device->mac_address === null) {
            return 'Add a valid MAC address before enrolling this device.';
        }

        if (! $modelMatched) {
            return 'Select a brand, family, and model from the current provisioning catalog.';
        }

        if ($catalogProvider === null && $device->provisioning_enrollment_provider === null) {
            return 'The selected model does not advertise a manufacturer provisioning provider.';
        }

        return $adapterAvailable ? null : self::ADAPTER_UNAVAILABLE;
    }

    private function recordFailure(
        User $actor,
        SwitchAccount $account,
        SwitchDevice $device,
        string $action,
        string $provider,
        Throwable $exception,
        ?string $ipAddress,
    ): void {
        $this->audit->record(
            $actor,
            $account,
            $action,
            'failed',
            $device->switch_resource_id,
            ['provider' => $provider, 'error_type' => class_basename($exception)],
            $ipAddress,
        );
    }
}
