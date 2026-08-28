<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Devices;

final readonly class DeviceRecordingSourceData
{
    public function __construct(
        public ?DeviceRecordingParametersData $any = null,
        public ?DeviceRecordingParametersData $onnet = null,
        public ?DeviceRecordingParametersData $offnet = null,
    ) {}

    /** @return array<string, array<string, bool|int|string>> */
    public function toSwitchData(): array
    {
        return array_filter([
            'any' => $this->any?->toSwitchData(),
            'onnet' => $this->onnet?->toSwitchData(),
            'offnet' => $this->offnet?->toSwitchData(),
        ], static fn (?array $value): bool => $value !== null);
    }
}
