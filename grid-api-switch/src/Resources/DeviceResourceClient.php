<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use GridPbx\Switch\Dto\DeviceSnapshot;
use GridPbx\Switch\Dto\DeviceWriteData;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class DeviceResourceClient
{
    public function __construct(private SwitchClient $client)
    {
    }

    public function create(string $accountId, DeviceWriteData $device): DeviceSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $payload = $this->client->request(
            'PUT',
            sprintf('accounts/%s/devices', rawurlencode($accountId)),
            ['json' => ['data' => $device->toSwitchData()]],
        );

        return $this->snapshot($payload);
    }

    public function update(string $accountId, string $deviceId, DeviceWriteData $device): DeviceSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $deviceId = $this->requiredIdentifier($deviceId, 'device');
        $payload = $this->client->request(
            'POST',
            sprintf(
                'accounts/%s/devices/%s',
                rawurlencode($accountId),
                rawurlencode($deviceId),
            ),
            ['json' => ['data' => $device->toSwitchData()]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $deviceId) {
            throw new InvalidSwitchPayloadException('Switch device response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function delete(string $accountId, string $deviceId): void
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $deviceId = $this->requiredIdentifier($deviceId, 'device');
        $this->client->request(
            'DELETE',
            sprintf(
                'accounts/%s/devices/%s',
                rawurlencode($accountId),
                rawurlencode($deviceId),
            ),
        );
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): DeviceSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch device response data must be an object.');
        }

        return new DeviceSnapshot($data);
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
