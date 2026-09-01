<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Agents\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class AgentCallStatistic
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $agentId,
        array $data,
    ) {
        if ($agentId === '') {
            throw new InvalidSwitchPayloadException('Switch agent statistic must contain an agent identifier.');
        }

        $this->totalCalls = $this->nonNegativeInteger($data, 'total_calls');
        $this->answeredCalls = $this->nonNegativeInteger($data, 'answered_calls', 0);
        $this->missedCalls = $this->nonNegativeInteger($data, 'missed_calls', 0);

        if ($this->answeredCalls > $this->totalCalls || $this->missedCalls > $this->totalCalls) {
            throw new InvalidSwitchPayloadException('Switch agent statistic call counts are inconsistent.');
        }
    }

    public int $totalCalls;

    public int $answeredCalls;

    public int $missedCalls;

    /** @return array{agent_id: string, total_calls: int, answered_calls: int, missed_calls: int} */
    public function toArray(): array
    {
        return [
            'agent_id' => $this->agentId,
            'total_calls' => $this->totalCalls,
            'answered_calls' => $this->answeredCalls,
            'missed_calls' => $this->missedCalls,
        ];
    }

    /** @param array<string, mixed> $data */
    private function nonNegativeInteger(array $data, string $key, ?int $default = null): int
    {
        $value = $data[$key] ?? $default;

        if (! is_int($value) || $value < 0) {
            throw new InvalidSwitchPayloadException(sprintf('Switch agent statistic %s must be a non-negative integer.', $key));
        }

        return $value;
    }
}
