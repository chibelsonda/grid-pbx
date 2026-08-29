<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

final readonly class AccountCallRecordingData
{
    public function __construct(
        public ?AccountRecordingRulesData $account = null,
        public ?AccountRecordingRulesData $endpoint = null,
    ) {}

    /** @return array<string, array<string, array<string, array<string, bool|int|string>>>> */
    public function toSwitchData(): array
    {
        return array_filter([
            'account' => $this->account?->toSwitchData(),
            'endpoint' => $this->endpoint?->toSwitchData(),
        ], static fn (?array $value): bool => $value !== null);
    }
}
