<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

final readonly class DeviceOutboundFlagsData
{
    /**
     * @param  list<string>  $static
     * @param  list<string>  $dynamic
     */
    public function __construct(
        public array $static = [],
        public array $dynamic = [],
    ) {}

    /** @return list<string> */
    public function toSwitchData(): array
    {
        return array_values(array_unique(array_merge($this->static, $this->dynamic)));
    }
}
