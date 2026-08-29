<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\TemporalRules\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class TemporalRuleSnapshot extends EntitySnapshot
{
    public string $name;
    public string $cycle;
    public int $interval;
    public ?int $startDate;
    public ?int $timeWindowStart;
    public ?int $timeWindowStop;
    public ?bool $enabled;
    public ?int $month;
    public ?string $ordinal;
    /** @var list<int> */ public array $days;
    /** @var list<string> */ public array $weekdays;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $name = $data['name'] ?? null;
        $cycle = $data['cycle'] ?? null;

        if (! is_string($name) || trim($name) === '' || ! is_string($cycle) || ! in_array($cycle, TemporalRuleWriteData::CYCLES, true)) {
            throw new InvalidSwitchPayloadException('Switch temporal rule response is missing required metadata.');
        }

        $this->name = $name;
        $this->cycle = $cycle;
        $this->interval = max(1, (int) ($data['interval'] ?? 1));
        $this->startDate = is_int($data['start_date'] ?? null) ? $data['start_date'] : null;
        $this->timeWindowStart = is_int($data['time_window_start'] ?? null) ? $data['time_window_start'] : null;
        $this->timeWindowStop = is_int($data['time_window_stop'] ?? null) ? $data['time_window_stop'] : null;
        $this->enabled = is_bool($data['enabled'] ?? null) ? $data['enabled'] : null;
        $this->month = is_int($data['month'] ?? null) ? $data['month'] : null;
        $this->ordinal = is_string($data['ordinal'] ?? null) ? $data['ordinal'] : null;
        $this->days = array_values(array_filter($data['days'] ?? [], static fn (mixed $day): bool => is_int($day)));
        $this->weekdays = array_values(array_map(
            static fn (string $day): string => $day === 'wensday' ? 'wednesday' : $day,
            array_filter($data['wdays'] ?? [], static fn (mixed $day): bool => is_string($day)),
        ));
    }
}
