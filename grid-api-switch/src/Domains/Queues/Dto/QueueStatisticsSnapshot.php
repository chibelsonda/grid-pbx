<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Queues\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class QueueStatisticsSnapshot
{
    /** @var list<QueueCallStatistic> */
    public array $statistics;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $currentTimestamp = $data['current_timestamp'] ?? null;
        $statistics = $data['stats'] ?? null;

        if (! is_int($currentTimestamp) || $currentTimestamp < 1) {
            throw new InvalidSwitchPayloadException('Switch queue statistics current_timestamp must be a positive integer.');
        }

        if (! is_array($statistics) || ! array_is_list($statistics)) {
            throw new InvalidSwitchPayloadException('Switch queue statistics stats must be a list.');
        }

        $this->currentTimestamp = $currentTimestamp;
        $this->statistics = array_map(
            static function (mixed $statistic): QueueCallStatistic {
                if (! is_array($statistic)) {
                    throw new InvalidSwitchPayloadException('Switch queue statistic must be an object.');
                }

                return new QueueCallStatistic($statistic);
            },
            $statistics,
        );
    }

    public int $currentTimestamp;

    /** @return array{current_timestamp: int, statistics: list<array{queue_id: string, status: string, entered_timestamp: int|null, wait_time: int|null, talk_time: int|null}>} */
    public function toArray(): array
    {
        return [
            'current_timestamp' => $this->currentTimestamp,
            'statistics' => array_map(
                static fn (QueueCallStatistic $statistic): array => $statistic->toArray(),
                $this->statistics,
            ),
        ];
    }
}
