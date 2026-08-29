<?php

namespace App\Domains\Devices\Services;

use App\Domains\Devices\Contracts\SwitchProvisioningCatalogGateway;
use App\Domains\Devices\Models\SwitchDevice;
use Illuminate\Validation\ValidationException;

class ProvisioningModelCapabilitiesService
{
    /** @var list<string> */
    private const DEFAULT_KEY_TYPES = [
        'line',
        'presence',
        'personal_parking',
        'speed_dial',
        'parking',
    ];

    public function __construct(
        private readonly SwitchProvisioningCatalogGateway $catalogGateway,
    ) {}

    /** @return array{matched: bool, max_keys: int|null, max_expansion_modules: int|null, keys_per_expansion_module: int|null, total_keys: int|null, supported_key_types: list<string>, value_sources: list<string>, manufacturer_provider: string|null} */
    public function forDevice(SwitchDevice $device): array
    {
        $model = $this->findModel($device);

        if ($model === null) {
            return $this->unknownCapabilities();
        }

        $maxKeys = $this->nullableInteger($model['max_keys'] ?? null);
        $maxExpansionModules = $this->nullableInteger($model['max_expansion_modules'] ?? null);
        $keysPerExpansionModule = $this->nullableInteger($model['keys_per_expansion_module'] ?? null);
        $supportedKeyTypes = array_values(array_intersect(
            self::DEFAULT_KEY_TYPES,
            is_array($model['supported_key_types'] ?? null) ? $model['supported_key_types'] : [],
        ));

        return [
            'matched' => true,
            'max_keys' => $maxKeys,
            'max_expansion_modules' => $maxExpansionModules,
            'keys_per_expansion_module' => $keysPerExpansionModule,
            'total_keys' => $maxKeys === null
                ? null
                : $maxKeys + (($maxExpansionModules ?? 0) * ($keysPerExpansionModule ?? 0)),
            'supported_key_types' => $supportedKeyTypes === []
                ? self::DEFAULT_KEY_TYPES
                : $supportedKeyTypes,
            'value_sources' => array_values(array_filter(
                is_array($model['value_sources'] ?? null) ? $model['value_sources'] : [],
                static fn (mixed $source): bool => is_string($source),
            )),
            'manufacturer_provider' => is_string($model['manufacturer_provider'] ?? null)
                ? $model['manufacturer_provider']
                : null,
        ];
    }

    /**
     * @param  list<array{category: string, position: int, type: string, value: string|int|null, label: string|null}>  $keys
     *
     * @throws ValidationException
     */
    public function assertKeysFit(SwitchDevice $device, array $keys): void
    {
        $capabilities = $this->forDevice($device);

        if (! $capabilities['matched']) {
            if (count($keys) > 100) {
                throw ValidationException::withMessages([
                    'line_keys' => ['Devices without matched model metadata support at most 100 line-key assignments.'],
                ]);
            }

            return;
        }

        $errors = [];
        $totalKeys = $capabilities['total_keys'];
        $positions = [];

        if ($totalKeys !== null && count($keys) > $totalKeys) {
            $errors['line_keys'][] = "The selected model supports at most {$totalKeys} line-key assignments.";
        }

        foreach ($keys as $index => $key) {
            if ($totalKeys !== null && $key['position'] >= $totalKeys) {
                $errors["line_keys.{$index}.position"][] = $totalKeys === 0
                    ? 'The selected model does not expose programmable line keys.'
                    : 'Use a position from 0 to '.($totalKeys - 1).'.';
            }

            if (isset($positions[$key['position']])) {
                $errors["line_keys.{$index}.position"][] = 'Each physical model position may be assigned only once.';
            }

            $positions[$key['position']] = true;

            if (! in_array($key['type'], $capabilities['supported_key_types'], true)) {
                $errors["line_keys.{$index}.type"][] = 'This line-key type is not supported by the selected model.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed>|null */
    private function findModel(SwitchDevice $device): ?array
    {
        if ($device->make === null || $device->model === null) {
            return null;
        }

        $catalog = $this->catalogGateway->catalog();

        if (! ($catalog['available'] ?? false)) {
            return null;
        }

        foreach ($catalog['brands'] as $brand) {
            if (! $this->matches($device->make, $brand['id'] ?? null, $brand['name'] ?? null)) {
                continue;
            }

            foreach ($brand['families'] as $family) {
                if ($device->endpoint_family !== null
                    && ! $this->matches($device->endpoint_family, $family['id'] ?? null, $family['name'] ?? null)) {
                    continue;
                }

                foreach ($family['models'] as $model) {
                    if ($this->matches(
                        $device->model,
                        $model['id'] ?? null,
                        $model['name'] ?? null,
                        $model['template_id'] ?? null,
                    )) {
                        return $model;
                    }
                }
            }
        }

        return null;
    }

    private function matches(string $selected, mixed ...$candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && mb_strtolower($selected) === mb_strtolower($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    /** @return array{matched: false, max_keys: null, max_expansion_modules: null, keys_per_expansion_module: null, total_keys: null, supported_key_types: list<string>, value_sources: list<string>, manufacturer_provider: null} */
    private function unknownCapabilities(): array
    {
        return [
            'matched' => false,
            'max_keys' => null,
            'max_expansion_modules' => null,
            'keys_per_expansion_module' => null,
            'total_keys' => null,
            'supported_key_types' => self::DEFAULT_KEY_TYPES,
            'value_sources' => [],
            'manufacturer_provider' => null,
        ];
    }
}
