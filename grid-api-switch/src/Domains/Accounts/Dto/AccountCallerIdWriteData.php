<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

final readonly class AccountCallerIdWriteData
{
    public function __construct(
        public ?string $internalName,
        public ?string $internalNumber,
        public ?string $externalName,
        public ?string $externalNumber,
        public ?string $emergencyName,
        public ?string $emergencyNumber,
    ) {}

    /** @return array<string, array{name: string, number: string}> */
    public function toSwitchData(): array
    {
        return [
            'internal' => [
                'name' => $this->internalName ?? '',
                'number' => $this->internalNumber ?? '',
            ],
            'external' => [
                'name' => $this->externalName ?? '',
                'number' => $this->externalNumber ?? '',
            ],
            'emergency' => [
                'name' => $this->emergencyName ?? '',
                'number' => $this->emergencyNumber ?? '',
            ],
        ];
    }
}
