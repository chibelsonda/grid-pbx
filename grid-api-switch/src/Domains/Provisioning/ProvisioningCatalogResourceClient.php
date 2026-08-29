<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Provisioning;

use GridPbx\Switch\Domains\Provisioning\Dto\ProvisioningBrandSnapshot;
use GridPbx\Switch\Domains\Provisioning\Dto\ProvisioningFamilySnapshot;
use GridPbx\Switch\Domains\Provisioning\Dto\ProvisioningModelSnapshot;
use GridPbx\Switch\Domains\Provisioning\ProvisionerClient;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class ProvisioningCatalogResourceClient
{
    private const LINE_KEY_TYPES = [
        'line',
        'presence',
        'personal_parking',
        'speed_dial',
        'parking',
    ];

    public function __construct(private ProvisionerClient $client) {}

    /** @return list<ProvisioningBrandSnapshot> */
    public function all(): array
    {
        $payload = $this->client->get('phones');
        $data = $payload['data'] ?? $payload;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch provisioner catalog data must be an object.');
        }

        $brands = [];

        foreach ($data as $brandId => $brand) {
            if (! is_string($brandId) || ! is_array($brand)) {
                continue;
            }

            $families = [];

            foreach (($brand['families'] ?? []) as $familyId => $family) {
                if (! is_string($familyId) || ! is_array($family)) {
                    continue;
                }

                $models = [];

                foreach (($family['models'] ?? []) as $modelId => $model) {
                    if (! is_string($modelId) || ! is_array($model)) {
                        continue;
                    }

                    $models[] = new ProvisioningModelSnapshot(
                        id: $modelId,
                        name: $this->name($model['name'] ?? null, $modelId),
                        templateId: $this->optionalString($model['id'] ?? null),
                        maxKeys: $this->optionalInteger($model['max_keys'] ?? null, 0, 1000),
                        maxExpansionModules: $this->optionalInteger(
                            $model['max_expansion_modules'] ?? $model['max_exp_modules'] ?? null,
                            0,
                            20,
                        ),
                        keysPerExpansionModule: $this->optionalInteger(
                            $model['keys_per_expansion_module'] ?? $model['max_keys_exp_module'] ?? null,
                            0,
                            1000,
                        ),
                        supportedKeyTypes: $this->supportedKeyTypes(
                            $model['supported_key_types'] ?? $model['key_types'] ?? null,
                        ),
                        valueSources: $this->safeIdentifiers($model['value_sources'] ?? null),
                        manufacturerProvider: $this->safeIdentifier(
                            $model['manufacturer_provider'] ?? $model['ztp_manufacturer'] ?? null,
                        ),
                    );
                }

                usort($models, static fn ($left, $right): int => strcasecmp($left->name, $right->name));

                $families[] = new ProvisioningFamilySnapshot(
                    id: $familyId,
                    name: $this->name($family['name'] ?? null, $familyId),
                    models: $models,
                );
            }

            usort($families, static fn ($left, $right): int => strcasecmp($left->name, $right->name));

            $brands[] = new ProvisioningBrandSnapshot(
                id: $brandId,
                name: $this->name($brand['name'] ?? null, $brandId),
                families: $families,
            );
        }

        usort($brands, static fn ($left, $right): int => strcasecmp($left->name, $right->name));

        return $brands;
    }

    private function name(mixed $name, string $fallback): string
    {
        return is_string($name) && trim($name) !== '' ? trim($name) : $fallback;
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function optionalInteger(mixed $value, int $minimum, int $maximum): ?int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1)) {
            return null;
        }

        $integer = (int) $value;

        return $integer >= $minimum && $integer <= $maximum ? $integer : null;
    }

    /** @return list<string> */
    private function supportedKeyTypes(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_intersect(self::LINE_KEY_TYPES, $this->safeIdentifiers($values)));
    }

    /** @return list<string> */
    private function safeIdentifiers(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $safe = [];

        foreach (array_slice($values, 0, 32) as $value) {
            $identifier = $this->safeIdentifier($value);

            if ($identifier !== null && ! in_array($identifier, $safe, true)) {
                $safe[] = $identifier;
            }
        }

        return $safe;
    }

    private function safeIdentifier(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            && mb_strlen($value) <= 64
            && preg_match('/^[A-Za-z0-9_.:-]+$/', $value) === 1
                ? $value
                : null;
    }
}
