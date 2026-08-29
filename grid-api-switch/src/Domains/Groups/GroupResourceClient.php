<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Groups;

use Generator;
use GridPbx\Switch\Domains\Groups\Dto\GroupSnapshot;
use GridPbx\Switch\Domains\Groups\Dto\GroupWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class GroupResourceClient
{
    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Switch page size must be greater than zero.');
        }
    }

    /** @return Generator<int, GroupSnapshot> */
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

            $payload = $this->client->request('GET', sprintf('accounts/%s/groups', rawurlencode($accountId)), ['query' => $query]);
            $data = $payload['data'] ?? null;

            if (! is_array($data)) {
                throw new InvalidSwitchPayloadException('Switch group collection response data must be an array.');
            }

            foreach ($data as $summary) {
                $id = is_array($summary) ? ($summary['id'] ?? null) : null;

                if (! is_string($id) || $id === '') {
                    throw new InvalidSwitchPayloadException('Switch group collection entry must contain an id.');
                }

                yield $this->get($accountId, $id);
            }

            $next = $payload['next_start_key'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;

            if ($cursor !== null && isset($seen[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch group pagination returned a repeated cursor.');
            }

            if ($cursor !== null) {
                $seen[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    public function get(string $accountId, string $groupId): GroupSnapshot
    {
        return $this->snapshot($this->client->request('GET', $this->path($accountId, $groupId)));
    }

    public function create(string $accountId, GroupWriteData $group): GroupSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');

        return $this->snapshot($this->client->request('PUT', sprintf('accounts/%s/groups', rawurlencode($accountId)), [
            'json' => ['data' => $group->toSwitchData()],
        ]));
    }

    public function update(string $accountId, string $groupId, GroupWriteData $group): GroupSnapshot
    {
        $snapshot = $this->snapshot($this->client->request('POST', $this->path($accountId, $groupId), [
            'json' => ['data' => $group->toSwitchData()],
        ]));

        if ($snapshot->id !== $groupId) {
            throw new InvalidSwitchPayloadException('Switch group response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function delete(string $accountId, string $groupId): void
    {
        $this->client->request('DELETE', $this->path($accountId, $groupId));
    }

    private function path(string $accountId, string $groupId): string
    {
        return sprintf('accounts/%s/groups/%s', rawurlencode($this->requiredIdentifier($accountId, 'account')), rawurlencode($this->requiredIdentifier($groupId, 'group')));
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): GroupSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch group response data must be an object.');
        }

        return new GroupSnapshot($data);
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
