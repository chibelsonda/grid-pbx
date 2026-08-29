<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

final readonly class AccountRecordingSourceData
{
    public function __construct(
        public ?AccountRecordingParametersData $any = null,
        public ?AccountRecordingParametersData $onnet = null,
        public ?AccountRecordingParametersData $offnet = null,
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
