<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

final readonly class DeviceMetaflowsData
{
    public function __construct(
        public ?string $bindingDigit = null,
        public ?int $digitTimeout = null,
        public ?string $listenOn = null,
        public ?array $numbers = null,
        public ?array $patterns = null,
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_filter([
            'binding_digit' => $this->bindingDigit,
            'digit_timeout' => $this->digitTimeout,
            'listen_on' => $this->listenOn,
            'numbers' => $this->numbers === null ? null : (object) $this->numbers,
            'patterns' => $this->patterns === null ? null : (object) $this->patterns,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
