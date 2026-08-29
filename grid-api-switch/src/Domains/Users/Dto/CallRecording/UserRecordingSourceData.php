<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\CallRecording;

final readonly class UserRecordingSourceData
{
    public function __construct(
        public UserRecordingParametersData $any,
        public UserRecordingParametersData $onnet,
        public UserRecordingParametersData $offnet,
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function toSwitchData(): array
    {
        return [
            'any' => $this->any->toSwitchData(),
            'onnet' => $this->onnet->toSwitchData(),
            'offnet' => $this->offnet->toSwitchData(),
        ];
    }
}
