<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Queues\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class QueueCallStatistic
{
    private const STATUSES = ['waiting', 'handled', 'abandoned', 'processed'];

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $this->queueId = $this->requiredString($data, 'queue_id', 'Queue-ID');
        $this->status = strtolower($this->requiredString($data, 'status', 'Status'));

        if (! in_array($this->status, self::STATUSES, true)) {
            throw new InvalidSwitchPayloadException('Switch queue statistic contains an unsupported status.');
        }

        $this->enteredTimestamp = $this->optionalInteger($data, 'entered_timestamp', 'Entered-Timestamp');
        $this->waitTime = $this->optionalNonNegativeInteger($data, 'wait_time', 'Wait-Time');
        $this->talkTime = $this->optionalNonNegativeInteger($data, 'talk_time', 'Talk-Time');
    }

    public string $queueId;

    public string $status;

    public ?int $enteredTimestamp;

    public ?int $waitTime;

    public ?int $talkTime;

    /** @return array{queue_id: string, status: string, entered_timestamp: int|null, wait_time: int|null, talk_time: int|null} */
    public function toArray(): array
    {
        return [
            'queue_id' => $this->queueId,
            'status' => $this->status,
            'entered_timestamp' => $this->enteredTimestamp,
            'wait_time' => $this->waitTime,
            'talk_time' => $this->talkTime,
        ];
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $publicKey, string $eventKey): string
    {
        $value = $data[$publicKey] ?? $data[$eventKey] ?? null;

        if (! is_string($value) || $value === '') {
            throw new InvalidSwitchPayloadException(sprintf('Switch queue statistic must contain %s.', $publicKey));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function optionalInteger(array $data, string $publicKey, string $eventKey): ?int
    {
        $value = $data[$publicKey] ?? $data[$eventKey] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value)) {
            throw new InvalidSwitchPayloadException(sprintf('Switch queue statistic %s must be an integer.', $publicKey));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function optionalNonNegativeInteger(array $data, string $publicKey, string $eventKey): ?int
    {
        $value = $this->optionalInteger($data, $publicKey, $eventKey);

        if ($value !== null && $value < 0) {
            throw new InvalidSwitchPayloadException(sprintf('Switch queue statistic %s cannot be negative.', $publicKey));
        }

        return $value;
    }
}
