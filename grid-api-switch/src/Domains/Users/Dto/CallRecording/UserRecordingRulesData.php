<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\CallRecording;

final readonly class UserRecordingRulesData
{
    public function __construct(
        public UserRecordingSourceData $any,
        public UserRecordingSourceData $inbound,
        public UserRecordingSourceData $outbound,
    ) {}

    /** @return array<string, array<string, array<string, mixed>>> */
    public function toSwitchData(): array
    {
        return [
            'any' => $this->any->toSwitchData(),
            'inbound' => $this->inbound->toSwitchData(),
            'outbound' => $this->outbound->toSwitchData(),
        ];
    }
}
