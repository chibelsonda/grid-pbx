<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\Profile;

final readonly class UserProfileAddressData
{
    /** @param list<string> $types */
    public function __construct(
        public string $address,
        public array $types = [],
    ) {}

    /** @return array{address: string, types: list<string>} */
    public function toSwitchData(): array
    {
        return ['address' => $this->address, 'types' => $this->types];
    }
}
