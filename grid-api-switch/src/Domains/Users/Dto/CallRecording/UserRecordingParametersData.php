<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\CallRecording;

final readonly class UserRecordingParametersData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public bool $enabled,
        public string $format,
        public ?int $minimumSeconds = null,
        public bool $recordOnAnswer = false,
        public bool $recordOnBridge = false,
        public ?int $sampleRate = null,
        public ?int $timeLimit = null,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_merge($this->preservedOptions, array_filter([
            'enabled' => $this->enabled,
            'format' => $this->format,
            'record_min_sec' => $this->minimumSeconds,
            'record_on_answer' => $this->recordOnAnswer,
            'record_on_bridge' => $this->recordOnBridge,
            'record_sample_rate' => $this->sampleRate,
            'time_limit' => $this->timeLimit,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
