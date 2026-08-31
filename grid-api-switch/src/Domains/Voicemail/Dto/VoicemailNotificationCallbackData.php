<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Voicemail\Dto;

use InvalidArgumentException;

final readonly class VoicemailNotificationCallbackData
{
    /**
     * @param  list<int>  $schedule
     * @param  array<string, mixed>  $preservedOptions
     */
    public function __construct(
        public bool $disabled = false,
        public ?string $number = null,
        public ?int $attempts = null,
        public ?int $intervalSeconds = null,
        public ?int $timeoutSeconds = null,
        public array $schedule = [],
        public array $preservedOptions = [],
    ) {
        foreach ([$this->attempts, $this->intervalSeconds, $this->timeoutSeconds] as $value) {
            if ($value !== null && $value < 0) {
                throw new InvalidArgumentException('Switch voicemail callback values cannot be negative.');
            }
        }

        foreach ($this->schedule as $interval) {
            if (! is_int($interval) || $interval < 0) {
                throw new InvalidArgumentException('Switch voicemail callback schedule must contain non-negative integers.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_merge($this->preservedOptions, array_filter([
            'disabled' => $this->disabled,
            'number' => $this->number,
            'attempts' => $this->attempts,
            'interval_s' => $this->intervalSeconds,
            'timeout_s' => $this->timeoutSeconds,
            'schedule' => $this->schedule,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /** @param array<string, mixed> $data */
    public static function fromSwitchData(array $data): self
    {
        return new self(
            disabled: (bool) ($data['disabled'] ?? false),
            number: is_string($data['number'] ?? null) && $data['number'] !== '' ? $data['number'] : null,
            attempts: is_int($data['attempts'] ?? null) ? $data['attempts'] : null,
            intervalSeconds: is_int($data['interval_s'] ?? null) ? $data['interval_s'] : null,
            timeoutSeconds: is_int($data['timeout_s'] ?? null) ? $data['timeout_s'] : null,
            schedule: array_values(array_filter(
                is_array($data['schedule'] ?? null) ? $data['schedule'] : [],
                static fn (mixed $interval): bool => is_int($interval) && $interval >= 0,
            )),
        );
    }
}
