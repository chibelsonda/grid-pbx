<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use GridPbx\Switch\Dto\Devices\DeviceSnapshot;
use GridPbx\Switch\Dto\Devices\DeviceStatus;
use GridPbx\Switch\Dto\Devices\DeviceWriteData;
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

    public function get(string $accountId, string $deviceId): DeviceSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $deviceId = $this->requiredIdentifier($deviceId, 'device');
        $payload = $this->client->request(
            'GET',
            sprintf(
                'accounts/%s/devices/%s',
                rawurlencode($accountId),
                rawurlencode($deviceId),
            ),
        );

        return $this->snapshot($payload);
    }

    public function update(string $accountId, string $deviceId, DeviceWriteData $device): DeviceSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $deviceId = $this->requiredIdentifier($deviceId, 'device');
        $current = $this->get($accountId, $deviceId)->toArray();
        unset($current['id']);

        if ($device->ownerId === null) {
            unset($current['owner_id']);
        }

        $payload = $this->client->request(
            'POST',
            sprintf(
                'accounts/%s/devices/%s',
                rawurlencode($accountId),
                rawurlencode($deviceId),
            ),
            ['json' => ['data' => $this->mergePreservingUnknownFields(
                $this->removeEmptyArrays($current),
                $device->toSwitchData(),
            )]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $deviceId) {
            throw new InvalidSwitchPayloadException('Switch device response id does not match the requested resource.');
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function mergePreservingUnknownFields(array $current, array $updates): array
    {
        foreach ($updates as $key => $value) {
            $existing = $current[$key] ?? null;

            if (in_array($key, ['custom_sip_headers', 'dial_plan', 'music_on_hold', 'outbound_flags'], true)) {
                $current[$key] = $value;

                continue;
            }

            if (
                is_array($existing)
                && is_array($value)
                && ! array_is_list($existing)
                && ! array_is_list($value)
            ) {
                $current[$key] = $this->mergePreservingUnknownFields($existing, $value);

                continue;
            }

            $current[$key] = $value;
        }

        return $current;
    }

    /**
     * PHP's associative JSON decoding cannot distinguish `{}` from `[]` once
     * an empty Switch object reaches an array DTO. Omitting empty structures on
     * a full-document update preserves their empty semantics without changing
     * the JSON type and failing schema validation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
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

    /** @return list<DeviceStatus> */
    public function statuses(string $accountId): array
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $payload = $this->client->request(
            'GET',
            sprintf('accounts/%s/devices/status', rawurlencode($accountId)),
        );
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch device status response data must be an array.');
        }

        return array_map(
            static function (mixed $status): DeviceStatus {
                if (! is_array($status)) {
                    throw new InvalidSwitchPayloadException('Switch device status entries must be objects.');
                }

                return new DeviceStatus($status);
            },
            array_values($data),
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
