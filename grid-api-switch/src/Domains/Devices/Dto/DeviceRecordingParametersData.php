<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

final readonly class DeviceRecordingParametersData
{
    public function __construct(
        public ?bool $enabled = null,
        public ?string $format = null,
        public ?int $minimumSeconds = null,
        public ?bool $recordOnAnswer = null,
        public ?bool $recordOnBridge = null,
        public ?int $sampleRate = null,
        public ?int $timeLimit = null,
    ) {}

    /** @return array<string, bool|int|string> */
    public function toSwitchData(): array
    {
        return array_filter([
            'enabled' => $this->enabled,
            'format' => $this->format,
            'record_min_sec' => $this->minimumSeconds,
            'record_on_answer' => $this->recordOnAnswer,
            'record_on_bridge' => $this->recordOnBridge,
            'record_sample_rate' => $this->sampleRate,
            'time_limit' => $this->timeLimit,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
