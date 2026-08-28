<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use Generator;
use GridPbx\Switch\Dto\Blacklists\BlacklistSnapshot;
use GridPbx\Switch\Dto\Blacklists\BlacklistWriteData;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class BlacklistResourceClient
{
    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) throw new InvalidArgumentException('Switch page size must be greater than zero.');
    }

    /** @return Generator<int, BlacklistSnapshot> */
    public function allDetails(string $accountId): Generator
    {
        $accountId = $this->required($accountId, 'account');
        $cursor = null; $seen = [];
        do {
            $query = ['paginate' => 'true', 'page_size' => $this->pageSize];
            if ($cursor !== null) $query['start_key'] = $cursor;
            $payload = $this->client->request('GET', sprintf('accounts/%s/blacklists', rawurlencode($accountId)), ['query' => $query]);
            $data = $payload['data'] ?? null;
            if (! is_array($data)) throw new InvalidSwitchPayloadException('Switch blacklist collection response data must be an array.');
            foreach ($data as $summary) {
                $id = is_array($summary) ? ($summary['id'] ?? null) : null;
                if (! is_string($id) || $id === '') throw new InvalidSwitchPayloadException('Switch blacklist collection entry must contain an id.');
                yield $this->get($accountId, $id);
            }
            $next = $payload['next_start_key'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;
            if ($cursor !== null && isset($seen[$cursor])) throw new InvalidSwitchPayloadException('Switch blacklist pagination returned a repeated cursor.');
            if ($cursor !== null) $seen[$cursor] = true;
        } while ($cursor !== null);
    }

    public function get(string $accountId, string $blacklistId): BlacklistSnapshot { return $this->snapshot($this->client->request('GET', $this->path($accountId, $blacklistId))); }
    public function create(string $accountId, BlacklistWriteData $blacklist): BlacklistSnapshot
    {
        $accountId = $this->required($accountId, 'account');
        return $this->snapshot($this->client->request('PUT', sprintf('accounts/%s/blacklists', rawurlencode($accountId)), ['json' => ['data' => $blacklist->toSwitchData()]]));
    }
    public function update(string $accountId, string $blacklistId, BlacklistWriteData $blacklist): BlacklistSnapshot
    {
        $snapshot = $this->snapshot($this->client->request('POST', $this->path($accountId, $blacklistId), ['json' => ['data' => $blacklist->toSwitchData()]]));
        if ($snapshot->id !== $blacklistId) throw new InvalidSwitchPayloadException('Switch blacklist response id does not match the requested resource.');
        return $snapshot;
    }
    public function delete(string $accountId, string $blacklistId): void { $this->client->request('DELETE', $this->path($accountId, $blacklistId)); }
    private function path(string $accountId, string $blacklistId): string { return sprintf('accounts/%s/blacklists/%s', rawurlencode($this->required($accountId, 'account')), rawurlencode($this->required($blacklistId, 'blacklist'))); }
    /** @param array<string, mixed> $payload */ private function snapshot(array $payload): BlacklistSnapshot
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data)) throw new InvalidSwitchPayloadException('Switch blacklist response data must be an object.');
        return new BlacklistSnapshot($data);
    }
    private function required(string $id, string $name): string { if ($id === '') throw new InvalidArgumentException("Switch {$name} identifier is required."); return $id; }
}
