<?php

namespace App\Domains\Devices\Gateways;

use App\Domains\Devices\Contracts\ManufacturerProvisioningEnrollmentGateway;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Organizations\Models\SwitchAccount;
use LogicException;

class UnavailableManufacturerProvisioningEnrollmentGateway implements ManufacturerProvisioningEnrollmentGateway
{
    public function supports(string $provider): bool
    {
        return false;
    }

    public function enroll(SwitchAccount $account, SwitchDevice $device, string $provider): void
    {
        throw new LogicException('The manufacturer provisioning enrollment adapter is unavailable.');
    }

    public function detach(SwitchAccount $account, SwitchDevice $device, string $provider): void
    {
        throw new LogicException('The manufacturer provisioning enrollment adapter is unavailable.');
    }
}
