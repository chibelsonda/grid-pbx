<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\LineKeys;

use GridPbx\Switch\Domains\LineKeys\Dto\DeviceProvisioningSnapshot;
use GridPbx\Switch\Domains\LineKeys\Dto\LineKeyWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class LineKeyResourceClient
{
    public function __construct(private SwitchClient $client) {}

    public function get(string $accountId, string $deviceId): DeviceProvisioningSnapshot
    {
        $payload = $this->client->request('GET', $this->path($accountId, $deviceId));

        return $this->snapshot($payload, $deviceId);
    }

    /** @param list<LineKeyWriteData> $keys */
    public function update(string $accountId, string $deviceId, array $keys): DeviceProvisioningSnapshot
    {
        $combo = [];
        $feature = [];

        foreach ($keys as $key) {
            if (! $key instanceof LineKeyWriteData) {
                throw new InvalidArgumentException('Switch line-key updates require LineKeyWriteData values.');
            }
        }

        $currentPayload = $this->client->request('GET', $this->path($accountId, $deviceId));
        $current = $currentPayload['data'] ?? null;

        if (! is_array($current)) {
            throw new InvalidSwitchPayloadException('Switch device provisioning response data must be an object.');
        }

        unset($current['id']);
        $current = $this->removeEmptyArrays($current);
        $provision = is_array($current['provision'] ?? null) ? $current['provision'] : [];

        foreach ($keys as $key) {
            $category = $key->category === 'combo' ? 'combo_keys' : 'feature_keys';
            $existing = $provision[$category][(string) $key->position] ?? null;
            $data = $this->mergeKeyData(is_array($existing) ? $existing : [], $key->toSwitchData());

            if ($key->category === 'combo') {
                $combo[(string) $key->position] = $data;
            } else {
                $feature[(string) $key->position] = $data;
            }
        }

        $provision['combo_keys'] = (object) $combo;
        $provision['feature_keys'] = (object) $feature;
        $current['provision'] = $provision;

        $payload = $this->client->request('POST', $this->path($accountId, $deviceId), [
            'json' => ['data' => $current],
        ]);

        return $this->snapshot($payload, $deviceId);
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload, string $deviceId): DeviceProvisioningSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch device provisioning response data must be an object.');
        }

        $snapshot = new DeviceProvisioningSnapshot($data);

        if ($snapshot->deviceId !== $deviceId) {
            throw new InvalidSwitchPayloadException('Switch device response id does not match the requested resource.');
        }

        return $snapshot;
    }

    private function path(string $accountId, string $deviceId): string
    {
        if ($accountId === '' || $deviceId === '') {
            throw new InvalidArgumentException('Switch account and device identifiers are required.');
        }

        return sprintf(
            'accounts/%s/devices/%s',
            rawurlencode($accountId),
            rawurlencode($deviceId),
        );
    }

    /** @param array<string, mixed> $data */
    private function removeEmptyArrays(array $data): array
    {
        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if ($value === []) {
                unset($data[$key]);

                continue;
            }

            $data[$key] = $this->removeEmptyArrays($value);

            if ($data[$key] === []) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $write
     * @return array<string, mixed>
     */
    private function mergeKeyData(array $existing, array $write): array
    {
        $unknown = array_diff_key($existing, array_flip(['type', 'value']));

        if (is_array($existing['value'] ?? null) && is_array($write['value'] ?? null)) {
            $unknownValue = array_diff_key($existing['value'], array_flip(['label', 'value']));
            $write['value'] = array_merge($unknownValue, $write['value']);
        }

        return array_merge($unknown, $write);
    }
}
