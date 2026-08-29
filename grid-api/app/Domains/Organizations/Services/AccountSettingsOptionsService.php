<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\SwitchAccount;

class AccountSettingsOptionsService
{
    public function __construct(private readonly SwitchAccountGateway $gateway) {}

    /**
     * @return array{
     *   restrictions: list<array{key: string, label: string, emergency: bool}>,
     *   callflows: list<array{id: string, name: string, description: string|null}>,
     *   metaflow_resources: array<string, list<array<string, mixed>>>
     * }
     */
    public function get(SwitchAccount $account): array
    {
        $callflows = $account->callflows()
            ->whereNotNull('switch_resource_id')
            ->where('switch_resource_id', '!=', '')
            ->orderBy('name')
            ->orderBy('callflow_id')
            ->get(['id', 'name', 'numbers'])
            ->map(static fn ($callflow): array => [
                'id' => $callflow->id,
                'name' => $callflow->name,
                'description' => collect($callflow->numbers)->filter()->join(', ') ?: null,
            ])
            ->all();

        return [
            'restrictions' => $this->gateway->restrictionClassifiers($account),
            'callflows' => $callflows,
            'metaflow_resources' => [
                'media' => $account->media()
                    ->whereNotNull('switch_resource_id')
                    ->where('switch_resource_id', '!=', '')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(static fn ($media): array => ['id' => $media->id, 'name' => $media->name])
                    ->all(),
                'callflows' => $callflows,
                'devices' => $account->devices()
                    ->whereNotNull('switch_resource_id')
                    ->where('switch_resource_id', '!=', '')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(static fn ($device): array => ['id' => $device->id, 'name' => $device->name])
                    ->all(),
                'extensions' => $account->extensions()
                    ->whereNotNull('switch_resource_id')
                    ->where('switch_resource_id', '!=', '')
                    ->orderBy('display_name')
                    ->orderBy('extension_id')
                    ->get(['id', 'display_name', 'extension'])
                    ->map(static fn ($extension): array => [
                        'id' => $extension->id,
                        'display_name' => $extension->display_name,
                        'extension' => $extension->extension,
                    ])
                    ->all(),
            ],
        ];
    }
}
