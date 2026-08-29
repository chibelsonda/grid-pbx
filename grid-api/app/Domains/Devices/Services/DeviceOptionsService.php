<?php

namespace App\Domains\Devices\Services;

use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Contracts\SwitchProvisioningCatalogGateway;
use App\Domains\Organizations\Models\SwitchAccount;

class DeviceOptionsService
{
    public function __construct(
        private readonly SwitchDeviceGateway $gateway,
        private readonly SwitchProvisioningCatalogGateway $provisioningCatalog,
        private readonly DeviceSchemaCompatibilityService $schemaCompatibility,
    ) {}

    /** @return array<string, mixed> */
    public function get(SwitchAccount $account): array
    {
        return [
            'extensions' => $account->extensions()
                ->orderBy('display_name')
                ->orderBy('extension_id')
                ->get(['id', 'display_name', 'extension'])
                ->map(static fn ($extension): array => [
                    'id' => $extension->id,
                    'display_name' => $extension->display_name,
                    'extension' => $extension->extension,
                ])
                ->all(),
            'media' => $account->media()
                ->orderBy('name')
                ->orderBy('media_id')
                ->get(['id', 'name'])
                ->map(static fn ($media): array => [
                    'id' => $media->id,
                    'name' => $media->name,
                ])
                ->all(),
            'metaflow_resources' => [
                'callflows' => $account->callflows()
                    ->orderBy('name')
                    ->get(['id', 'name', 'numbers'])
                    ->map(static fn ($callflow): array => [
                        'id' => $callflow->id,
                        'name' => $callflow->name,
                        'description' => collect($callflow->numbers)->filter()->join(', '),
                    ])->all(),
                'devices' => $account->devices()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(static fn ($device): array => [
                        'id' => $device->id,
                        'name' => $device->name,
                    ])->all(),
            ],
            'caller_id_numbers' => $account->phoneNumbers()
                ->orderBy('number')
                ->get(['id', 'number', 'features', 'cnam_display_name', 'e911_status'])
                ->map(static fn ($phoneNumber): array => [
                    'id' => $phoneNumber->id,
                    'number' => $phoneNumber->number,
                    'display_name' => $phoneNumber->cnam_display_name,
                    'e911_enabled' => $phoneNumber->isE911Enabled(),
                ])
                ->all(),
            'provisioning_catalog' => $this->provisioningCatalog->catalog(),
            'device_schema' => $this->schemaCompatibility->current(),
            'restrictions' => $this->gateway->restrictionClassifiers($account),
        ];
    }
}
