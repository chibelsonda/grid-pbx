<?php

namespace App\Domains\Devices\Contracts;

interface SwitchProvisioningCatalogGateway
{
    /** @return array{available: bool, reason: string|null, brands: list<array{id: string, name: string, families: list<array{id: string, name: string, models: list<array{id: string, name: string, template_id: string|null, max_keys?: int|null, max_expansion_modules?: int|null, keys_per_expansion_module?: int|null, supported_key_types?: list<string>, value_sources?: list<string>, manufacturer_provider?: string|null}>}>}>} */
    public function catalog(): array;
}
