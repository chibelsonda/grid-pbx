<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Devices;

final readonly class DeviceOutboundFlagsData
{
    /**
     * @param list<string> $static
     * @param list<string> $dynamic
     */
    public function __construct(
        public array $static = [],
        public array $dynamic = [],
    ) {}

    /** @return array{static: list<string>, dynamic: list<string>} */
    public function toSwitchData(): array
    {
        return ['static' => $this->static, 'dynamic' => $this->dynamic];
    }
}
