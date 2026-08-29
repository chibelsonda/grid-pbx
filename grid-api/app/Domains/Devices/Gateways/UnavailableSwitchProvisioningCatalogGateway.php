<?php

namespace App\Domains\Devices\Gateways;

use App\Domains\Devices\Contracts\SwitchProvisioningCatalogGateway;

class UnavailableSwitchProvisioningCatalogGateway implements SwitchProvisioningCatalogGateway
{
    public function catalog(): array
    {
        return [
            'available' => false,
            'reason' => 'Provisioning catalog discovery is not configured. Manual hardware values remain available.',
            'brands' => [],
        ];
    }
}
