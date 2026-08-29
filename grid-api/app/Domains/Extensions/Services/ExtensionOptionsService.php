<?php

namespace App\Domains\Extensions\Services;

use App\Domains\Devices\Services\StarterDevicePolicy;
use App\Domains\Organizations\Models\SwitchAccount;
use DateTimeZone;

class ExtensionOptionsService
{
    public function __construct(private readonly StarterDevicePolicy $starterDevices) {}

    /** @return array<string, mixed> */
    public function get(SwitchAccount $account): array
    {
        return [
            'account_defaults' => [
                'timezone' => $account->timezone,
            ],
            'timezones' => DateTimeZone::listIdentifiers(),
            'languages' => [
                ['value' => 'en-US', 'label' => 'English (United States)'],
                ['value' => 'fr-FR', 'label' => 'French (France)'],
                ['value' => 'de-DE', 'label' => 'German (Germany)'],
                ['value' => 'ru-RU', 'label' => 'Russian (Russia)'],
                ['value' => 'es-ES', 'label' => 'Spanish (Spain)'],
            ],
            'presence_ids' => $account->extensions()
                ->whereNotNull('extension')
                ->where('extension', '<>', '')
                ->orderBy('extension')
                ->get(['extension', 'display_name'])
                ->unique('extension')
                ->values()
                ->map(static fn ($extension): array => [
                    'value' => $extension->extension,
                    'label' => trim(sprintf(
                        '%s%s',
                        $extension->extension,
                        $extension->display_name ? " — {$extension->display_name}" : '',
                    )),
                ])
                ->all(),
            'starter_device' => $this->starterDevices->capabilities(),
        ];
    }
}
