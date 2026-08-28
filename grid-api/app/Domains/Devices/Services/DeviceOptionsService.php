<?php

namespace App\Domains\Devices\Services;

use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Organizations\Models\SwitchAccount;

class DeviceOptionsService
{
    public function __construct(private readonly SwitchDeviceGateway $gateway) {}

    /** @return array{extensions: list<array{id: string, display_name: string|null, extension: string|null}>, media: list<array{id: string, name: string|null}>, restrictions: list<array{key: string, label: string, emergency: bool}>} */
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
            'restrictions' => $this->gateway->restrictionClassifiers($account),
        ];
    }
}
