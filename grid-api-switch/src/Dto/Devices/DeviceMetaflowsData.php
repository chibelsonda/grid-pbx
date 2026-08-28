<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Devices;

final readonly class DeviceMetaflowsData
{
    public function __construct(
        public ?string $bindingDigit = null,
        public ?int $digitTimeout = null,
        public ?string $listenOn = null,
    ) {}

    /** @return array<string, int|string> */
    public function toSwitchData(): array
    {
        return array_filter([
            'binding_digit' => $this->bindingDigit,
            'digit_timeout' => $this->digitTimeout,
            'listen_on' => $this->listenOn,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
