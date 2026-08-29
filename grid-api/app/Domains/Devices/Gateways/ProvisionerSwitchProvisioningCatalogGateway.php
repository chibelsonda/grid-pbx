<?php

namespace App\Domains\Devices\Gateways;

use App\Domains\Devices\Contracts\SwitchProvisioningCatalogGateway;
use GridPbx\Switch\Domains\Provisioning\ProvisioningCatalogResourceClient;
use GridPbx\Switch\Shared\Exceptions\SwitchException;

class ProvisionerSwitchProvisioningCatalogGateway implements SwitchProvisioningCatalogGateway
{
    public function __construct(private readonly ProvisioningCatalogResourceClient $catalog) {}

    public function catalog(): array
    {
        try {
            $brands = array_map(static fn ($brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
                'families' => array_map(static fn ($family): array => [
                    'id' => $family->id,
                    'name' => $family->name,
                    'models' => array_map(static fn ($model): array => [
                        'id' => $model->id,
                        'name' => $model->name,
                        'template_id' => $model->templateId,
                        'max_keys' => $model->maxKeys,
                        'max_expansion_modules' => $model->maxExpansionModules,
                        'keys_per_expansion_module' => $model->keysPerExpansionModule,
                        'supported_key_types' => $model->supportedKeyTypes,
                        'value_sources' => $model->valueSources,
                        'manufacturer_provider' => $model->manufacturerProvider,
                    ], $family->models),
                ], $brand->families),
            ], $this->catalog->all());

            return ['available' => true, 'reason' => null, 'brands' => $brands];
        } catch (SwitchException) {
            return [
                'available' => false,
                'reason' => 'The configured provisioning catalog is currently unavailable. Manual hardware values remain available.',
                'brands' => [],
            ];
        }
    }
}
