<?php

namespace App\Domains\LineKeys\Services;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Devices\Services\ProvisioningModelCapabilitiesService;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Eloquent\Collection;

class LineKeyService
{
    public function __construct(
        private readonly ProvisioningModelCapabilitiesService $modelCapabilities,
    ) {}

    /** @return Collection<int, SwitchDevice> */
    public function devices(SwitchAccount $account, ?string $search): Collection
    {
        return $account->devices()
            ->with(['lineKeys' => fn ($query) => $query->orderBy('category')->orderBy('position')])
            ->when($search, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('make', 'like', "%{$search}%")
                    ->orWhere('endpoint_family', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhereHas('lineKeys', fn ($query) => $query->where('label', 'like', "%{$search}%")
                        ->orWhere('value', 'like', "%{$search}%"));
            }))
            ->orderByRaw('name IS NULL')
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, mixed> */
    public function preview(SwitchDevice $device): array
    {
        $device->load(['lineKeys' => fn ($query) => $query->orderBy('category')->orderBy('position')]);
        $capable = $device->make !== null && $device->model !== null && $device->mac_address !== null;
        $enabled = (bool) config('switch.line_key_mutations_enabled', false);
        $modelCapabilities = $this->modelCapabilities->forDevice($device);

        return [
            'device' => $device,
            'line_keys' => $device->lineKeys,
            'capability' => [
                'preview_available' => true,
                'apply_available' => $capable && $enabled,
                'reason' => ! $capable
                    ? 'The device needs an endpoint brand, model, and MAC address before it can be provisioned.'
                    : ($enabled ? null : 'Line-key mutations are disabled by server configuration.'),
                'model' => $modelCapabilities,
            ],
            'value_choices' => $this->valueChoices($device, $modelCapabilities['value_sources']),
            'payload_preview' => [
                'provision' => [
                    'combo_keys' => $this->payloadKeys($device, 'combo'),
                    'feature_keys' => $this->payloadKeys($device, 'feature'),
                ],
            ],
        ];
    }

    /**
     * @param  list<string>  $sources
     * @return list<array{id: string, source: string, value: string, label: string, description: string|null}>
     */
    private function valueChoices(SwitchDevice $device, array $sources): array
    {
        $account = $device->switchAccount;
        $choices = [];

        if (in_array('extensions', $sources, true) || in_array('users', $sources, true)) {
            foreach ($account->extensions()
                ->whereNotNull('switch_resource_id')
                ->orderBy('display_name')
                ->limit(250)
                ->get(['id', 'switch_resource_id', 'display_name', 'extension']) as $extension) {
                $choices[] = [
                    'id' => $extension->id,
                    'source' => 'extensions',
                    'value' => $extension->switch_resource_id,
                    'label' => $extension->display_name,
                    'description' => $extension->extension,
                ];
            }
        }

        if (in_array('devices', $sources, true)) {
            foreach ($account->devices()
                ->whereNotNull('switch_resource_id')
                ->orderBy('name')
                ->limit(250)
                ->get(['id', 'switch_resource_id', 'name']) as $candidate) {
                $choices[] = [
                    'id' => $candidate->id,
                    'source' => 'devices',
                    'value' => $candidate->switch_resource_id,
                    'label' => $candidate->name ?? 'Unnamed device',
                    'description' => 'Device',
                ];
            }
        }

        return $choices;
    }

    /** @return array<string, mixed>|object */
    private function payloadKeys(SwitchDevice $device, string $category): array|object
    {
        $keys = $device->lineKeys
            ->where('category', $category)
            ->mapWithKeys(function ($key): array {
                $data = ['type' => $key->type];

                if ($key->value !== null) {
                    $data['value'] = $key->label === null
                        ? $key->value
                        : ['label' => $key->label, 'value' => $key->value];
                }

                return [(string) $key->position => $data];
            })
            ->all();

        return $keys === [] ? (object) [] : $keys;
    }
}
