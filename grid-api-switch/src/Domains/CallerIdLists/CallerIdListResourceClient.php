<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\CallerIdLists;

use Generator;
use GridPbx\Switch\Domains\CallerIdLists\Dto\CallerIdListDetails;
use GridPbx\Switch\Domains\CallerIdLists\Dto\CallerIdListEntrySnapshot;
use GridPbx\Switch\Domains\CallerIdLists\Dto\CallerIdListEntryWriteData;
use GridPbx\Switch\Domains\CallerIdLists\Dto\CallerIdListSnapshot;
use GridPbx\Switch\Domains\CallerIdLists\Dto\CallerIdListWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class CallerIdListResourceClient
{
    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Switch page size must be greater than zero.');
        }
    }

    /** @return Generator<int, CallerIdListDetails> */
    public function allDetails(string $accountId): Generator
    {
        $accountId = $this->required($accountId, 'account');
        $cursor = null;
        $seen = [];

        do {
            $payload = $this->client->request(
                'GET',
                sprintf('accounts/%s/lists', rawurlencode($accountId)),
                ['query' => $this->pageQuery($cursor)],
            );
            $data = $payload['data'] ?? null;

            if (! is_array($data)) {
                throw new InvalidSwitchPayloadException('Switch Caller-ID List collection response data must be an array.');
            }

            foreach ($data as $summary) {
                $id = is_array($summary) ? ($summary['id'] ?? null) : null;

                if (! is_string($id) || $id === '') {
                    throw new InvalidSwitchPayloadException('Switch Caller-ID List collection entry must contain an id.');
                }

                yield $this->details($accountId, $id);
            }

            $cursor = $this->nextCursor($payload, $seen, 'Caller-ID List');
        } while ($cursor !== null);
    }

    public function details(string $accountId, string $listId): CallerIdListDetails
    {
        return new CallerIdListDetails(
            $this->get($accountId, $listId),
            iterator_to_array($this->entries($accountId, $listId), false),
        );
    }

    public function get(string $accountId, string $listId): CallerIdListSnapshot
    {
        $payload = $this->client->request('GET', $this->path($accountId, $listId));
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch Caller-ID List response data must be an object.');
        }

        return new CallerIdListSnapshot($data);
    }

    public function create(string $accountId, CallerIdListWriteData $list): CallerIdListSnapshot
    {
        return $this->listSnapshot($this->client->request(
            'PUT',
            sprintf('accounts/%s/lists', rawurlencode($this->required($accountId, 'account'))),
            ['json' => ['data' => $list->toSwitchData()]],
        ));
    }

    public function update(string $accountId, string $listId, CallerIdListWriteData $list): CallerIdListSnapshot
    {
        return $this->listSnapshot($this->client->request(
            'POST',
            $this->path($accountId, $listId),
            ['json' => ['data' => $list->toSwitchData()]],
        ));
    }

    public function delete(string $accountId, string $listId): void
    {
        $this->client->request('DELETE', $this->path($accountId, $listId));
    }

    public function createEntry(
        string $accountId,
        string $listId,
        CallerIdListEntryWriteData $entry,
    ): CallerIdListEntrySnapshot {
        $listId = $this->required($listId, 'Caller-ID List');

        return $this->entrySnapshot($this->client->request(
            'PUT',
            $this->path($accountId, $listId).'/entries',
            ['json' => ['data' => $entry->toSwitchData() + ['list_id' => $listId]]],
        ));
    }

    public function updateEntry(
        string $accountId,
        string $listId,
        string $entryId,
        CallerIdListEntryWriteData $entry,
    ): CallerIdListEntrySnapshot {
        $listId = $this->required($listId, 'Caller-ID List');

        return $this->entrySnapshot($this->client->request(
            'POST',
            $this->entryPath($accountId, $listId, $entryId),
            ['json' => ['data' => $entry->toSwitchData() + ['list_id' => $listId]]],
        ));
    }

    public function deleteEntry(string $accountId, string $listId, string $entryId): void
    {
        $this->client->request('DELETE', $this->entryPath($accountId, $listId, $entryId));
    }

    /** @return Generator<int, CallerIdListEntrySnapshot> */
    public function entries(string $accountId, string $listId): Generator
    {
        $cursor = null;
        $seen = [];

        do {
            $payload = $this->client->request(
                'GET',
                $this->path($accountId, $listId).'/entries',
                ['query' => $this->pageQuery($cursor)],
            );
            $data = $payload['data'] ?? null;

            if (! is_array($data)) {
                throw new InvalidSwitchPayloadException('Switch Caller-ID List entries response data must be an array.');
            }

            foreach ($data as $entry) {
                if (! is_array($entry)) {
                    throw new InvalidSwitchPayloadException('Switch Caller-ID List entry data must be an object.');
                }

                $entryId = $entry['id'] ?? null;

                if (! is_string($entryId) || $entryId === '') {
                    throw new InvalidSwitchPayloadException('Switch Caller-ID List entry collection item must contain an id.');
                }

                // The entries view is deliberately a summary and omits editable fields in
                // some Crossbar versions. Hydrate the document before projecting it.
                yield $this->getEntry($accountId, $listId, $entryId);
            }

            $cursor = $this->nextCursor($payload, $seen, 'Caller-ID List entry');
        } while ($cursor !== null);
    }

    public function getEntry(string $accountId, string $listId, string $entryId): CallerIdListEntrySnapshot
    {
        $listId = $this->required($listId, 'Caller-ID List');
        $payload = $this->client->request('GET', $this->entryPath($accountId, $listId, $entryId));
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch Caller-ID List entry response data must be an object.');
        }

        $entryListId = $data['list_id'] ?? null;

        if ($entryListId !== null && $entryListId !== $listId) {
            throw new InvalidSwitchPayloadException('Switch Caller-ID List entry belongs to a different list.');
        }

        return new CallerIdListEntrySnapshot($data + ['list_id' => $listId]);
    }

    /** @return array<string, int|string> */
    private function pageQuery(?string $cursor): array
    {
        $query = ['paginate' => 'true', 'page_size' => $this->pageSize];

        if ($cursor !== null) {
            $query['start_key'] = $cursor;
        }

        return $query;
    }

    /** @param array<string, mixed> $payload @param array<string, true> $seen */
    private function nextCursor(array $payload, array &$seen, string $resource): ?string
    {
        $next = $payload['next_start_key'] ?? null;
        $cursor = is_string($next) && $next !== '' ? $next : null;

        if ($cursor !== null && isset($seen[$cursor])) {
            throw new InvalidSwitchPayloadException("Switch {$resource} pagination returned a repeated cursor.");
        }

        if ($cursor !== null) {
            $seen[$cursor] = true;
        }

        return $cursor;
    }

    private function path(string $accountId, string $listId): string
    {
        return sprintf(
            'accounts/%s/lists/%s',
            rawurlencode($this->required($accountId, 'account')),
            rawurlencode($this->required($listId, 'Caller-ID List')),
        );
    }

    private function entryPath(string $accountId, string $listId, string $entryId): string
    {
        return $this->path($accountId, $listId).'/entries/'.rawurlencode($this->required($entryId, 'Caller-ID List entry'));
    }

    /** @param array<string, mixed> $payload */
    private function listSnapshot(array $payload): CallerIdListSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch Caller-ID List response data must be an object.');
        }

        return new CallerIdListSnapshot($data);
    }

    /** @param array<string, mixed> $payload */
    private function entrySnapshot(array $payload): CallerIdListEntrySnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch Caller-ID List entry response data must be an object.');
        }

        return new CallerIdListEntrySnapshot($data);
    }

    private function required(string $id, string $name): string
    {
        if ($id === '') {
            throw new InvalidArgumentException("Switch {$name} identifier is required.");
        }

        return $id;
    }
}
