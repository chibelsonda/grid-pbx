<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Devices;

use GridPbx\Switch\Dto\Common\EntitySnapshot;

final readonly class DeviceSnapshot extends EntitySnapshot
{
    public ?string $ownerId;

    public ?string $name;

    public ?string $deviceType;

    public ?string $make;

    public ?string $model;

    public ?string $macAddress;

    public bool $enabled;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->ownerId = $this->nullableString($data['owner_id'] ?? null);
        $this->name = $this->nullableString($data['name'] ?? null);
        $this->deviceType = $this->nullableString($data['device_type'] ?? null);
        $this->make = $this->nullableString($data['make'] ?? null)
            ?? $this->nestedString('provision', 'endpoint_brand');
        $this->model = $this->nullableString($data['model'] ?? null)
            ?? $this->nestedString('provision', 'endpoint_model');
        $this->macAddress = $this->nullableString($data['mac_address'] ?? null)
            ?? $this->nestedString('provision', 'mac_address');
        $this->enabled = (bool) ($data['enabled'] ?? true);
    }
}
