<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use GridPbx\Switch\Dto\LineKeys\DeviceProvisioningSnapshot;
use GridPbx\Switch\Dto\LineKeys\LineKeyWriteData;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class LineKeyResourceClient
{
    public function __construct(private SwitchClient $client)
    {
    }

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

            if ($key->category === 'combo') {
                $combo[(string) $key->position] = $key->toSwitchData();
            } else {
                $feature[(string) $key->position] = $key->toSwitchData();
            }
        }

        $payload = $this->client->request('PATCH', $this->path($accountId, $deviceId), [
            'json' => ['data' => ['provision' => [
                'combo_keys' => (object) $combo,
                'feature_keys' => (object) $feature,
            ]]],
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
}
