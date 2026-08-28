<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\TemporalRules;

use InvalidArgumentException;

final readonly class TemporalRuleWriteData
{
    public const CYCLES = ['date', 'daily', 'weekly', 'monthly', 'yearly'];
    public const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    public const ORDINALS = ['every', 'first', 'second', 'third', 'fourth', 'fifth', 'last'];

    /** @param list<int> $days
     * @param list<string> $weekdays
     */
    public function __construct(
        public string $name,
        public string $cycle,
        public int $interval = 1,
        public ?int $startDate = null,
        public ?int $timeWindowStart = null,
        public ?int $timeWindowStop = null,
        public ?bool $enabled = null,
        public array $days = [],
        public array $weekdays = [],
        public ?int $month = null,
        public ?string $ordinal = null,
    ) {
        if (trim($this->name) === '' || ! in_array($this->cycle, self::CYCLES, true)) {
            throw new InvalidArgumentException('Switch temporal rule name and cycle are required.');
        }
        if ($this->interval < 1 || ($this->month !== null && ($this->month < 1 || $this->month > 12))) {
            throw new InvalidArgumentException('Switch temporal rule recurrence settings are invalid.');
        }
        foreach ([$this->timeWindowStart, $this->timeWindowStop] as $seconds) {
            if ($seconds !== null && ($seconds < 0 || $seconds > 86400)) {
                throw new InvalidArgumentException('Switch temporal rule time window is invalid.');
            }
        }
        if (array_filter($this->days, static fn (mixed $day): bool => ! is_int($day) || $day < 1 || $day > 31)
            || array_filter($this->weekdays, static fn (mixed $day): bool => ! is_string($day) || ! in_array($day, self::WEEKDAYS, true))
            || ($this->ordinal !== null && ! in_array($this->ordinal, self::ORDINALS, true))) {
            throw new InvalidArgumentException('Switch temporal rule recurrence values are invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_filter([
            'name' => $this->name, 'cycle' => $this->cycle, 'interval' => $this->interval,
            'start_date' => $this->startDate, 'time_window_start' => $this->timeWindowStart,
            'time_window_stop' => $this->timeWindowStop, 'enabled' => $this->enabled,
            'days' => $this->days === [] ? null : array_values($this->days),
            'wdays' => $this->weekdays === [] ? null : array_values($this->weekdays),
            'month' => $this->month, 'ordinal' => $this->ordinal,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
