<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

final readonly class DeviceFlagsData
{
    /** @param list<string> $flags */
    public function __construct(public array $flags = []) {}

    /** @return list<string> */
    public function toSwitchData(): array
    {
        return array_values(array_unique($this->flags));
    }
}
