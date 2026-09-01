<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Agents\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class AgentStatisticsSnapshot
{
    /** @var list<AgentCallStatistic> */
    public array $statistics;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        if (array_is_list($data) && $data !== []) {
            throw new InvalidSwitchPayloadException('Switch agent statistics response data must be an object.');
        }

        $statistics = [];

        foreach ($data as $agentId => $statistic) {
            if (! is_string($agentId) || ! is_array($statistic)) {
                throw new InvalidSwitchPayloadException('Switch agent statistics entries must be keyed objects.');
            }

            $statistics[] = new AgentCallStatistic($agentId, $statistic);
        }

        $this->statistics = $statistics;
    }

    /** @return list<array{agent_id: string, total_calls: int, answered_calls: int, missed_calls: int}> */
    public function toArray(): array
    {
        return array_map(
            static fn (AgentCallStatistic $statistic): array => $statistic->toArray(),
            $this->statistics,
        );
    }
}
