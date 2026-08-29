<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

final readonly class AccountRecordingRulesData
{
    public function __construct(
        public ?AccountRecordingSourceData $any = null,
        public ?AccountRecordingSourceData $inbound = null,
        public ?AccountRecordingSourceData $outbound = null,
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
