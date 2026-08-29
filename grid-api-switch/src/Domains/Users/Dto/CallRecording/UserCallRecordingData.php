<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\CallRecording;

final readonly class UserCallRecordingData
{
    public function __construct(
        public UserRecordingRulesData $account,
        public UserRecordingRulesData $endpoint,
    ) {}

    /** @return array<string, array<string, array<string, array<string, mixed>>>> */
    public function toSwitchData(): array
    {
        return [
            'account' => $this->account->toSwitchData(),
            'endpoint' => $this->endpoint->toSwitchData(),
        ];
    }
}
