<?php

namespace App\Domains\Devices\Contracts;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Organizations\Models\SwitchAccount;

interface ManufacturerProvisioningEnrollmentGateway
{
    public function supports(string $provider): bool;

    public function enroll(SwitchAccount $account, SwitchDevice $device, string $provider): void;

    public function detach(SwitchAccount $account, SwitchDevice $device, string $provider): void;
}
