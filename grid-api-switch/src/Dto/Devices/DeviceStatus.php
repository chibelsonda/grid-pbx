<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Devices;

use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;

final readonly class DeviceStatus
{
    public string $deviceId;

    public bool $registered;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $deviceId = $data['device_id'] ?? null;

        if (! is_string($deviceId) || $deviceId === '') {
            throw new InvalidSwitchPayloadException('Switch device status must contain a non-empty device_id.');
        }

        $this->deviceId = $deviceId;
        $this->registered = (bool) ($data['registered'] ?? false);
    }
}
