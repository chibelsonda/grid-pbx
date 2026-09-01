<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Agents\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class AgentAvailabilitySnapshot
{
    /** @var list<array{agent_id: string, status: string, timestamp: int}> */
    private array $agents;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $agents = [];

        foreach ($data as $agentId => $history) {
            if (! is_string($agentId) || $agentId === '' || ! is_array($history)) {
                throw new InvalidSwitchPayloadException('Switch agent availability must be keyed by Agent identifier.');
            }

            $entries = array_key_exists('status', $history) ? [$history] : array_values($history);
            $latest = null;

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    throw new InvalidSwitchPayloadException('Switch agent availability history entries must be objects.');
                }

                $status = $entry['status'] ?? null;
                $timestamp = $entry['timestamp'] ?? null;

                if (! is_string($status) || ! in_array($status, self::statuses(), true) || ! is_int($timestamp) || $timestamp < 0) {
                    throw new InvalidSwitchPayloadException('Switch agent availability entry is invalid.');
                }

                if ($latest === null || $timestamp > $latest['timestamp']) {
                    $latest = ['agent_id' => $agentId, 'status' => $status, 'timestamp' => $timestamp];
                }
            }

            if ($latest !== null) {
                $agents[] = $latest;
            }
        }

        $this->agents = $agents;
    }

    /** @return list<array{agent_id: string, status: string, timestamp: int}> */
    public function toArray(): array
    {
        return $this->agents;
    }

    /** @return list<string> */
    private static function statuses(): array
    {
        return [
            'ready',
            'logged_in',
            'logged_out',
            'connecting',
            'connected',
            'wrapup',
            'paused',
            'outbound',
            'unknown',
        ];
    }
}
