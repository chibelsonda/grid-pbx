<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Queues;

use Generator;
use GridPbx\Switch\Domains\Queues\Dto\QueueSnapshot;
use GridPbx\Switch\Domains\Queues\Dto\QueueWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class QueueResourceClient
{
    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Switch page size must be greater than zero.');
        }
    }

    /** @return Generator<int, QueueSnapshot> */
    public function allDetails(string $accountId): Generator
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $cursor = null;
        $seen = [];

        do {
            $query = ['paginate' => 'true', 'page_size' => $this->pageSize];

            if ($cursor !== null) {
                $query['start_key'] = $cursor;
            }

            $payload = $this->client->request('GET', sprintf('accounts/%s/queues', rawurlencode($accountId)), ['query' => $query]);
            $data = $payload['data'] ?? null;

            if (! is_array($data)) {
                throw new InvalidSwitchPayloadException('Switch queue collection response data must be an array.');
            }

            foreach ($data as $summary) {
                $id = is_array($summary) ? ($summary['id'] ?? null) : null;

                if (! is_string($id) || $id === '') {
                    throw new InvalidSwitchPayloadException('Switch queue collection entry must contain an id.');
                }

                yield $this->get($accountId, $id);
            }

            $next = $payload['next_start_key'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;

            if ($cursor !== null && isset($seen[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch queue pagination returned a repeated cursor.');
            }

            if ($cursor !== null) {
                $seen[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    public function get(string $accountId, string $queueId): QueueSnapshot
    {
        return $this->snapshot($this->client->request('GET', $this->path($accountId, $queueId)));
    }

    public function create(string $accountId, QueueWriteData $queue): QueueSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');

        return $this->snapshot($this->client->request('PUT', sprintf('accounts/%s/queues', rawurlencode($accountId)), [
            'json' => ['data' => $queue->toSwitchData()],
        ]));
    }

    public function update(string $accountId, string $queueId, QueueWriteData $queue): QueueSnapshot
    {
        $current = $this->get($accountId, $queueId);
        $data = $queue->toSwitchData($current->data);

        if ($current->maxPriority === null) {
            unset($data['max_priority']);
        } else {
            $data['max_priority'] = $current->maxPriority;
        }

        foreach (['cdr_url' => $current->cdrUrl, 'recording_url' => $current->recordingUrl] as $key => $value) {
            if ($value === null) {
                unset($data[$key]);
            } else {
                $data[$key] = $value;
            }
        }

        $snapshot = $this->snapshot($this->client->request('POST', $this->path($accountId, $queueId), [
            'json' => ['data' => $data],
        ]));

        if ($snapshot->id !== $queueId) {
            throw new InvalidSwitchPayloadException('Switch queue response id does not match the requested resource.');
        }

        return $snapshot;
    }

    /** @return list<string> */
    public function roster(string $accountId, string $queueId): array
    {
        $payload = $this->client->request('GET', $this->rosterPath($accountId, $queueId));
        $data = $payload['data'] ?? null;

        if (! is_array($data) || array_filter($data, static fn (mixed $id): bool => ! is_string($id) || $id === '')) {
            throw new InvalidSwitchPayloadException('Switch queue roster response must contain user identifiers.');
        }

        return array_values($data);
    }

    /** @param list<string> $agentIds */
    public function replaceRoster(string $accountId, string $queueId, array $agentIds): void
    {
        if (array_filter($agentIds, static fn (mixed $id): bool => ! is_string($id) || $id === '')) {
            throw new InvalidArgumentException('Switch queue roster must contain user identifiers.');
        }

        $this->client->request('POST', $this->rosterPath($accountId, $queueId), ['json' => ['data' => array_values($agentIds)]]);
    }

    public function delete(string $accountId, string $queueId): void
    {
        $this->client->request('DELETE', $this->path($accountId, $queueId));
    }

    private function path(string $accountId, string $queueId): string
    {
        return sprintf('accounts/%s/queues/%s', rawurlencode($this->requiredIdentifier($accountId, 'account')), rawurlencode($this->requiredIdentifier($queueId, 'queue')));
    }

    private function rosterPath(string $accountId, string $queueId): string
    {
        return $this->path($accountId, $queueId).'/roster';
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): QueueSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch queue response data must be an object.');
        }

        return new QueueSnapshot($data);
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
