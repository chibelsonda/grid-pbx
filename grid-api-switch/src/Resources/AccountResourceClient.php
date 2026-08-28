<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use Generator;
use GridPbx\Switch\Dto\Common\EntitySnapshot;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class AccountResourceClient
{
    public function __construct(
        private SwitchClient $client,
        private int $pageSize = 200,
    ) {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Switch page size must be greater than zero.');
        }
    }

    /** @return Generator<int, EntitySnapshot> */
    public function allDetails(string $accountId, AccountResource $resource): Generator
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $cursor = null;
        $seenCursors = [];

        do {
            $query = [
                'paginate' => 'true',
                'page_size' => $this->pageSize,
            ];

            if ($cursor !== null) {
                $query['start_key'] = $cursor;
            }

            $payload = $this->client->request(
                'GET',
                sprintf('accounts/%s/%s', rawurlencode($accountId), $resource->value),
                ['query' => $query],
            );
            $summaries = $payload['data'] ?? null;

            if (! is_array($summaries)) {
                throw new InvalidSwitchPayloadException('Switch collection response data must be an array.');
            }

            foreach ($summaries as $summary) {
                if (! is_array($summary)) {
                    throw new InvalidSwitchPayloadException('Switch collection entries must be objects.');
                }

                $resourceId = $summary['id'] ?? null;

                if (! is_string($resourceId) || $resourceId === '') {
                    throw new InvalidSwitchPayloadException('Switch collection entry must contain a non-empty string id.');
                }

                yield $this->find($accountId, $resource, $resourceId);
            }

            $nextCursor = $payload['next_start_key'] ?? null;
            $cursor = is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null;

            if ($cursor !== null && isset($seenCursors[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch pagination returned a repeated cursor.');
            }

            if ($cursor !== null) {
                $seenCursors[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    public function find(string $accountId, AccountResource $resource, string $resourceId): EntitySnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $resourceId = $this->requiredIdentifier($resourceId, 'resource');
        $payload = $this->client->request(
            'GET',
            sprintf(
                'accounts/%s/%s/%s',
                rawurlencode($accountId),
                $resource->value,
                rawurlencode($resourceId),
            ),
            ['query' => ['paginate' => 'false']],
        );
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch detail response data must be an object.');
        }

        $snapshot = $resource->snapshot($data);

        if ($snapshot->id !== $resourceId) {
            throw new InvalidSwitchPayloadException('Switch detail response id does not match the requested resource.');
        }

        return $snapshot;
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
