<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

use stdClass;

final readonly class DeviceCustomSipHeadersData
{
    /**
     * @param array<string, string> $inbound
     * @param array<string, string> $outbound
     */
    public function __construct(
        public array $inbound = [],
        public array $outbound = [],
    ) {}

    /** @return array{in: array<string, string>|stdClass, out: array<string, string>|stdClass} */
    public function toSwitchData(): array
    {
        return [
            'in' => $this->inbound === [] ? new stdClass() : $this->inbound,
            'out' => $this->outbound === [] ? new stdClass() : $this->outbound,
        ];
    }
}
