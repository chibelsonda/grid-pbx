<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

final readonly class DeviceCallRecordingData
{
    public function __construct(
        public ?DeviceRecordingSourceData $any = null,
        public ?DeviceRecordingSourceData $inbound = null,
        public ?DeviceRecordingSourceData $outbound = null,
    ) {}

    /** @return array<string, array<string, array<string, bool|int|string>>> */
    public function toSwitchData(): array
    {
        return array_filter([
            'any' => $this->any?->toSwitchData(),
            'inbound' => $this->inbound?->toSwitchData(),
            'outbound' => $this->outbound?->toSwitchData(),
        ], static fn (?array $value): bool => $value !== null);
    }
}
