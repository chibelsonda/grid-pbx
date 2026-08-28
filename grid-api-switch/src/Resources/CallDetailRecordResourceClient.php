<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use Generator;
use GridPbx\Switch\Dto\CallDetailRecords\CallDetailRecordSnapshot;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class CallDetailRecordResourceClient
{
    private const GREGORIAN_UNIX_OFFSET = 62167219200;

    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Switch CDR page size must be greater than zero.');
        }
    }

    /** @return Generator<int, CallDetailRecordSnapshot> */
    public function all(string $accountId, int $createdFromUnix, int $createdToUnix): Generator
    {
        if ($accountId === '') {
            throw new InvalidArgumentException('Switch account identifier is required.');
        }

        if ($createdFromUnix < 0 || $createdToUnix < $createdFromUnix) {
            throw new InvalidArgumentException('Switch CDR time range is invalid.');
        }

        $cursor = null;
        $seenCursors = [];

        do {
            $query = [
                'created_from' => $createdFromUnix + self::GREGORIAN_UNIX_OFFSET,
                'created_to' => $createdToUnix + self::GREGORIAN_UNIX_OFFSET,
                'paginate' => 'true',
                'page_size' => $this->pageSize,
            ];

            if ($cursor !== null) {
                $query['start_key'] = $cursor;
            }

            $payload = $this->client->request(
                'GET',
                sprintf('accounts/%s/cdrs', rawurlencode($accountId)),
                ['query' => $query],
            );
            $records = $payload['data'] ?? null;

            if (! is_array($records)) {
                throw new InvalidSwitchPayloadException('Switch CDR response data must be an array.');
            }

            foreach ($records as $record) {
                if (! is_array($record)) {
                    throw new InvalidSwitchPayloadException('Switch CDR entries must be objects.');
                }

                yield new CallDetailRecordSnapshot($record);
            }

            $nextCursor = $payload['next_start_key'] ?? null;
            $cursor = is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null;

            if ($cursor !== null && isset($seenCursors[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch CDR pagination returned a repeated cursor.');
            }

            if ($cursor !== null) {
                $seenCursors[$cursor] = true;
            }
        } while ($cursor !== null);
    }
}
