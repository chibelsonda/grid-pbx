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
        private readonly LineKeyReferenceResolver $references,
    ) {}

    /** @return Collection<int, SwitchDevice> */
    public function devices(SwitchAccount $account, ?string $search): Collection
    {
        $devices = $account->devices()
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

        $this->references->usePublicValues(
            $account,
            $devices->flatMap(static fn (SwitchDevice $device): Collection => $device->lineKeys),
        );

        return $devices;
    }

    /** @return array<string, mixed> */
    public function preview(SwitchDevice $device): array
    {
        $device->load(['lineKeys' => fn ($query) => $query->orderBy('category')->orderBy('position')]);
        $synchronized = $device->switch_resource_id !== null;
        $enabled = (bool) config('switch.line_key_mutations_enabled', false);
        $modelCapabilities = $this->modelCapabilities->forDevice($device);
        $hasHardwareIdentity = $device->make !== null
            && $device->model !== null
            && $device->mac_address !== null
            && (! $modelCapabilities['catalog_available'] || $device->endpoint_family !== null);
        $catalogMismatch = $modelCapabilities['catalog_available'] && ! $modelCapabilities['matched'];
        $capable = $synchronized && $hasHardwareIdentity && ! $catalogMismatch;
        $this->references->usePublicValues($device->switchAccount, $device->lineKeys);

        return [
            'device' => $device,
            'line_keys' => $device->lineKeys,
            'capability' => [
                'preview_available' => true,
                'apply_available' => $capable && $enabled,
                'reason' => $this->capabilityReason(
                    synchronized: $synchronized,
                    hasHardwareIdentity: $hasHardwareIdentity,
                    catalogAvailable: $modelCapabilities['catalog_available'],
                    catalogMismatch: $catalogMismatch,
                    enabled: $enabled,
                ),
                'model' => $modelCapabilities,
            ],
            'value_choices' => $this->references->choices($device->switchAccount),
            'payload_preview' => [
                'provision' => [
                    'combo_keys' => $this->payloadKeys($device, 'combo'),
                    'feature_keys' => $this->payloadKeys($device, 'feature'),
                ],
            ],
        ];
    }

    private function capabilityReason(
        bool $synchronized,
        bool $hasHardwareIdentity,
        bool $catalogAvailable,
        bool $catalogMismatch,
        bool $enabled,
    ): ?string {
        if (! $synchronized) {
            return 'The device must be synchronized from Switch before line keys can be applied.';
        }

        if (! $hasHardwareIdentity) {
            return $catalogAvailable
                ? 'The device needs an endpoint brand, family, model, and MAC address before it can be provisioned.'
                : 'The device needs an endpoint brand, model, and MAC address before it can be provisioned.';
        }

        if ($catalogMismatch) {
            return 'The device brand, family, and model do not match the current provisioning catalog.';
        }

        if (! $enabled) {
            return 'Line-key mutations are disabled by server configuration.';
        }

        return $catalogAvailable
            ? null
            : 'Model-specific key limits are unavailable; the editor is using the current Kazoo line-key schema and conservative limits.';
    }

    /** @return array<string, mixed>|object */
    private function payloadKeys(SwitchDevice $device, string $category): array|object
    {
        $keys = $device->lineKeys
            ->where('category', $category)
            ->mapWithKeys(function ($key): array {
                $data = ['type' => $key->type];

                if ($key->type !== 'line' && $key->value !== null) {
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
