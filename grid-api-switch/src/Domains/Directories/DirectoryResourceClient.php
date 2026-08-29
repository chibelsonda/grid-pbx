<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Directories;

use Generator;
use GridPbx\Switch\Domains\Directories\Dto\DirectorySnapshot;
use GridPbx\Switch\Domains\Directories\Dto\DirectoryWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class DirectoryResourceClient
{
    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Switch page size must be greater than zero.');
        }
    }

    /** @return Generator<int, DirectorySnapshot> */
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

            $payload = $this->client->request('GET', sprintf('accounts/%s/directories', rawurlencode($accountId)), ['query' => $query]);
            $data = $payload['data'] ?? null;

            if (! is_array($data)) {
                throw new InvalidSwitchPayloadException('Switch directory collection response data must be an array.');
            }

            foreach ($data as $summary) {
                $id = is_array($summary) ? ($summary['id'] ?? null) : null;

                if (! is_string($id) || $id === '') {
                    throw new InvalidSwitchPayloadException('Switch directory collection entry must contain an id.');
                }

                yield $this->get($accountId, $id);
            }

            $next = $payload['next_start_key'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;

            if ($cursor !== null && isset($seen[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch directory pagination returned a repeated cursor.');
            }

            if ($cursor !== null) {
                $seen[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    public function get(string $accountId, string $directoryId): DirectorySnapshot
    {
        return $this->snapshot($this->client->request('GET', $this->path($accountId, $directoryId)));
    }

    public function create(string $accountId, DirectoryWriteData $directory): DirectorySnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');

        return $this->snapshot($this->client->request('PUT', sprintf('accounts/%s/directories', rawurlencode($accountId)), [
            'json' => ['data' => $directory->toSwitchData()],
        ]));
    }

    public function update(string $accountId, string $directoryId, DirectoryWriteData $directory): DirectorySnapshot
    {
        $snapshot = $this->snapshot($this->client->request('POST', $this->path($accountId, $directoryId), [
            'json' => ['data' => $directory->toSwitchData()],
        ]));

        if ($snapshot->id !== $directoryId) {
            throw new InvalidSwitchPayloadException('Switch directory response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function delete(string $accountId, string $directoryId): void
    {
        $this->client->request('DELETE', $this->path($accountId, $directoryId));
    }

    private function path(string $accountId, string $directoryId): string
    {
        return sprintf('accounts/%s/directories/%s', rawurlencode($this->requiredIdentifier($accountId, 'account')), rawurlencode($this->requiredIdentifier($directoryId, 'directory')));
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): DirectorySnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch directory response data must be an object.');
        }

        return new DirectorySnapshot($data);
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
