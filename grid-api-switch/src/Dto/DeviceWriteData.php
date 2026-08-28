<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto;

use InvalidArgumentException;

final readonly class DeviceWriteData
{
    public function __construct(
        public string $name,
        public string $deviceType,
        public bool $enabled,
        public ?string $ownerId = null,
        public ?string $make = null,
        public ?string $model = null,
        public ?string $macAddress = null,
        private ?string $sipUsername = null,
        private ?string $sipPassword = null,
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch device name is required.');
        }

        if (trim($this->deviceType) === '') {
            throw new InvalidArgumentException('Switch device type is required.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = [
            'name' => $this->name,
            'device_type' => $this->deviceType,
            'enabled' => $this->enabled,
            'owner_id' => $this->ownerId,
        ];

        if ($this->macAddress !== null) {
            $data['mac_address'] = $this->macAddress;
        }

        $provision = array_filter([
            'endpoint_brand' => $this->make,
            'endpoint_model' => $this->model,
        ], static fn (?string $value): bool => $value !== null);

        if ($provision !== []) {
            $data['provision'] = $provision;
        }

        $sip = array_filter([
            'username' => $this->sipUsername,
            'password' => $this->sipPassword,
        ], static fn (?string $value): bool => $value !== null);

        if ($sip !== []) {
            $data['sip'] = $sip;
        }

        return $data;
    }
}
