<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Faxes;

use Generator;
use GridPbx\Switch\Domains\Faxes\Dto\FaxMessageSnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Shared\Http\BinaryResponse;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class FaxMessageResourceClient
{
    private const GREGORIAN_UNIX_OFFSET = 62167219200;

    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($pageSize < 1) {
            throw new InvalidArgumentException('Switch fax page size must be greater than zero.');
        }
    }

    /** @return Generator<int, FaxMessageSnapshot> */
    public function all(string $accountId, string $folder, int $createdFromUnix, int $createdToUnix): Generator
    {
        $accountId = $this->required($accountId, 'account');
        $folder = $this->folder($folder);
        if ($createdFromUnix < 0 || $createdToUnix < $createdFromUnix) {
            throw new InvalidArgumentException('Switch fax time range is invalid.');
        }
        $cursor = null;
        $seen = [];
        do {
            $query = ['created_from' => $createdFromUnix + self::GREGORIAN_UNIX_OFFSET, 'created_to' => $createdToUnix + self::GREGORIAN_UNIX_OFFSET, 'paginate' => 'true', 'page_size' => $this->pageSize];
            if ($cursor !== null) {
                $query['start_key'] = $cursor;
            }
            $payload = $this->client->request('GET', sprintf('accounts/%s/faxes/%s', rawurlencode($accountId), $folder), ['query' => $query]);
            $data = $payload['data'] ?? null;
            if (! is_array($data)) {
                throw new InvalidSwitchPayloadException('Switch fax collection response data must be an array.');
            }
            foreach ($data as $item) {
                if (! is_array($item)) {
                    throw new InvalidSwitchPayloadException('Switch fax collection entries must be objects.');
                } yield new FaxMessageSnapshot($item, $folder);
            }
            $next = $payload['next_start_key'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;
            if ($cursor !== null && isset($seen[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch fax pagination returned a repeated cursor.');
            } if ($cursor !== null) {
                $seen[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    public function get(string $accountId, string $folder, string $faxId): FaxMessageSnapshot
    {
        $folder = $this->folder($folder);
        $payload = $this->client->request('GET', $this->path($accountId, $folder, $faxId));
        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch fax response data must be an object.');
        } $snapshot = new FaxMessageSnapshot($data, $folder);
        if ($snapshot->id !== $faxId) {
            throw new InvalidSwitchPayloadException('Switch fax response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function document(string $accountId, string $folder, string $faxId, ?string $range = null): BinaryResponse
    {
        $headers = ['Accept' => 'application/pdf, image/tiff, application/octet-stream'];
        if ($range !== null) {
            $headers['Range'] = $range;
        }

        return $this->client->binary('GET', $this->path($accountId, $this->folder($folder), $faxId).'/attachment', ['query' => ['disposition' => 'inline'], 'headers' => $headers]);
    }

    private function path(string $accountId, string $folder, string $faxId): string
    {
        return sprintf('accounts/%s/faxes/%s/%s', rawurlencode($this->required($accountId, 'account')), $folder, rawurlencode($this->required($faxId, 'fax')));
    }

    private function folder(string $folder): string
    {
        if (! in_array($folder, ['inbox', 'outbox'], true)) {
            throw new InvalidArgumentException('Switch fax folder must be inbox or outbox.');
        }

        return $folder;
    }

    private function required(string $id, string $name): string
    {
        if ($id === '') {
            throw new InvalidArgumentException("Switch {$name} identifier is required.");
        }

        return $id;
    }
}
