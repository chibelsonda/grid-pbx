<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto;

use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;

abstract readonly class EntitySnapshot
{
    public string $id;

    public ?string $revision;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public array $data)
    {
        $id = $this->nullableString($data['id'] ?? null);

        if ($id === null) {
            throw new InvalidSwitchPayloadException('Switch entity data must contain a non-empty string id.');
        }

        $this->id = $id;
        $this->revision = $this->nullableString($data['_rev'] ?? null);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    protected function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<string> */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    protected function nestedString(string ...$path): ?string
    {
        $value = $this->data;

        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $this->nullableString($value);
    }
}
