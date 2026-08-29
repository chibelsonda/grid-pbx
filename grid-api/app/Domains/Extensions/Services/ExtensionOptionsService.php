<?php

namespace App\Domains\Extensions\Services;

use App\Domains\Devices\Services\StarterDevicePolicy;
use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use DateTimeZone;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtensionOptionsService
{
    public function __construct(
        private readonly StarterDevicePolicy $starterDevices,
        private readonly SwitchAccountGateway $accountGateway,
    ) {}

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
            'caller_id_numbers' => $account->phoneNumbers()
                ->orderBy('number')
                ->get(['id', 'number', 'cnam_display_name', 'features', 'e911_status'])
                ->map(static fn ($phoneNumber): array => [
                    'id' => $phoneNumber->id,
                    'number' => $phoneNumber->number,
                    'display_name' => $phoneNumber->cnam_display_name,
                    'e911_enabled' => $phoneNumber->isE911Enabled(),
                ])
                ->all(),
            'media' => $this->resources($account->media(), ['id', 'name']),
            'restrictions' => $this->accountGateway->restrictionClassifiers($account),
            'metaflow_resources' => [
                'media' => $this->resources($account->media(), ['id', 'name']),
                'callflows' => $this->resources(
                    $account->callflows(),
                    ['id', 'name', 'numbers'],
                    static fn ($callflow): array => [
                        'id' => $callflow->id,
                        'name' => $callflow->name,
                        'description' => collect($callflow->numbers)->filter()->join(', ') ?: null,
                    ],
                ),
                'devices' => $this->resources($account->devices(), ['id', 'name']),
                'extensions' => $this->resources(
                    $account->extensions(),
                    ['id', 'display_name', 'extension'],
                ),
            ],
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  (callable(mixed): array<string, mixed>)|null  $map
     * @return list<array<string, mixed>>
     */
    private function resources(HasMany $query, array $columns, ?callable $map = null): array
    {
        return $query
            ->whereNotNull('switch_resource_id')
            ->where('switch_resource_id', '!=', '')
            ->orderBy($columns[1])
            ->get($columns)
            ->map($map ?? static fn ($resource): array => $resource->only($columns))
            ->all();
    }
}
