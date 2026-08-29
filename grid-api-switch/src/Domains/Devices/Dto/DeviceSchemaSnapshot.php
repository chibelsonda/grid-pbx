<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

final readonly class DeviceSchemaSnapshot
{
    /** @param array<string, mixed> $schema */
    public function __construct(private array $schema) {}

    public function id(): ?string
    {
        $id = $this->schema['id'] ?? $this->schema['_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function supports(string $path): bool
    {
        return $this->property($path) !== null;
    }

    public function maxLength(string $path, int $fallback): int
    {
        $maximum = $this->property($path)['maxLength'] ?? null;

        return is_int($maximum) && $maximum > 0 ? $maximum : $fallback;
    }

    /** @param list<string> $fallback @return list<string> */
    public function enum(string $path, array $fallback = []): array
    {
        $values = $this->property($path)['enum'] ?? null;

        if (! is_array($values)) {
            return $fallback;
        }

        return array_values(array_filter($values, static fn (mixed $value): bool => is_string($value)));
    }

    /** @param list<string> $fallback @return list<string> */
    public function types(string $path, array $fallback = []): array
    {
        $property = $this->property($path);

        if ($property === null) {
            return $fallback;
        }

        $types = [];
        $declared = $property['type'] ?? null;

        if (is_string($declared)) {
            $types[] = $declared;
        } elseif (is_array($declared)) {
            foreach ($declared as $type) {
                if (is_string($type)) {
                    $types[] = $type;
                }
            }
        }

        foreach (($property['oneOf'] ?? []) as $variant) {
            if (is_array($variant) && is_string($variant['type'] ?? null)) {
                $types[] = $variant['type'];
            }
        }

        return $types === [] ? $fallback : array_values(array_unique($types));
    }

    /** @return array<string, mixed>|null */
    private function property(string $path): ?array
    {
        $schema = $this->schema;

        foreach (explode('.', $path) as $segment) {
            $properties = $schema['properties'] ?? null;

            if (! is_array($properties) || ! is_array($properties[$segment] ?? null)) {
                return null;
            }

            $schema = $properties[$segment];
        }

        return $schema;
    }
}
