<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Devices;

final readonly class DeviceCallerIdData
{
    public function __construct(
        public ?string $internalName = null,
        public ?string $internalNumber = null,
        public ?string $externalName = null,
        public ?string $externalNumber = null,
        public ?string $emergencyName = null,
        public ?string $emergencyNumber = null,
        public ?string $assertedName = null,
        public ?string $assertedNumber = null,
        public ?string $assertedRealm = null,
    ) {}

    /** @return array<string, array<string, string>> */
    public function toSwitchData(): array
    {
        return array_filter([
            'internal' => $this->identity($this->internalName, $this->internalNumber),
            'external' => $this->identity($this->externalName, $this->externalNumber),
            'emergency' => $this->identity($this->emergencyName, $this->emergencyNumber),
            'asserted' => array_filter([
                'name' => $this->assertedName,
                'number' => $this->assertedNumber,
                'realm' => $this->assertedRealm,
            ], static fn (?string $value): bool => $value !== null),
        ], static fn (array $value): bool => $value !== []);
    }

    /** @return array<string, string> */
    private function identity(?string $name, ?string $number): array
    {
        return array_filter([
            'name' => $name,
            'number' => $number,
        ], static fn (?string $value): bool => $value !== null);
    }
}
