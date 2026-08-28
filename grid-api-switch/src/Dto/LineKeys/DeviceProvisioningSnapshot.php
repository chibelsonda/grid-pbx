<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\LineKeys;

use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;

final readonly class DeviceProvisioningSnapshot
{
    public string $deviceId;

    public ?string $brand;

    public ?string $family;

    public ?string $model;

    /** @var list<LineKeySnapshot> */
    public array $lineKeys;

    /** @param array<string, mixed> $device */
    public function __construct(public array $device)
    {
        $deviceId = $device['id'] ?? null;

        if (! is_string($deviceId) || $deviceId === '') {
            throw new InvalidSwitchPayloadException('Switch device provisioning data requires a device id.');
        }

        $provision = is_array($device['provision'] ?? null) ? $device['provision'] : [];
        $this->deviceId = $deviceId;
        $this->brand = $this->stringValue($provision['endpoint_brand'] ?? null);
        $this->family = $this->stringValue($provision['endpoint_family'] ?? null);
        $this->model = $this->stringValue($provision['endpoint_model'] ?? null);
        $this->lineKeys = [
            ...$this->keys('combo', $provision['combo_keys'] ?? null),
            ...$this->keys('feature', $provision['feature_keys'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'device_id' => $this->deviceId,
            'endpoint_brand' => $this->brand,
            'endpoint_family' => $this->family,
            'endpoint_model' => $this->model,
            'line_keys' => array_map(
                static fn (LineKeySnapshot $key): array => $key->toArray(),
                $this->lineKeys,
            ),
        ];
    }

    /** @return list<LineKeySnapshot> */
    private function keys(string $category, mixed $keys): array
    {
        if (! is_array($keys)) {
            return [];
        }

        $snapshots = [];

        foreach ($keys as $position => $data) {
            if ((! is_int($position) && ! ctype_digit((string) $position)) || ! is_array($data)) {
                continue;
            }

            $snapshots[] = new LineKeySnapshot($category, (int) $position, $data);
        }

        usort($snapshots, static fn (LineKeySnapshot $left, LineKeySnapshot $right): int => $left->position <=> $right->position);

        return $snapshots;
    }

    private function stringValue(mixed $value): ?string
    {
        return (is_string($value) || is_int($value)) && (string) $value !== '' ? (string) $value : null;
    }
}
