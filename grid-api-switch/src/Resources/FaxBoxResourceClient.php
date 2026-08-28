<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use Generator;
use GridPbx\Switch\Dto\Faxes\FaxBoxSnapshot;
use GridPbx\Switch\Dto\Faxes\FaxBoxWriteData;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class FaxBoxResourceClient
{
    public function __construct(private SwitchClient $client, private int $pageSize = 200) { if ($pageSize < 1) throw new InvalidArgumentException('Switch page size must be greater than zero.'); }
    /** @return Generator<int, FaxBoxSnapshot> */
    public function allDetails(string $accountId): Generator
    {
        $accountId = $this->required($accountId, 'account'); $cursor = null; $seen = [];
        do {
            $query = ['paginate' => 'true', 'page_size' => $this->pageSize]; if ($cursor !== null) $query['start_key'] = $cursor;
            $payload = $this->client->request('GET', sprintf('accounts/%s/faxboxes', rawurlencode($accountId)), ['query' => $query]);
            $data = $payload['data'] ?? null; if (! is_array($data)) throw new InvalidSwitchPayloadException('Switch fax box collection response data must be an array.');
            foreach ($data as $summary) { $id = is_array($summary) ? ($summary['id'] ?? null) : null; if (! is_string($id) || $id === '') throw new InvalidSwitchPayloadException('Switch fax box collection entry must contain an id.'); yield $this->get($accountId, $id); }
            $next = $payload['next_start_key'] ?? null; $cursor = is_string($next) && $next !== '' ? $next : null;
            if ($cursor !== null && isset($seen[$cursor])) throw new InvalidSwitchPayloadException('Switch fax box pagination returned a repeated cursor.'); if ($cursor !== null) $seen[$cursor] = true;
        } while ($cursor !== null);
    }
    public function get(string $accountId, string $faxBoxId): FaxBoxSnapshot { return $this->snapshot($this->client->request('GET', $this->path($accountId, $faxBoxId))); }
    public function create(string $accountId, FaxBoxWriteData $data): FaxBoxSnapshot { $accountId = $this->required($accountId, 'account'); return $this->snapshot($this->client->request('PUT', sprintf('accounts/%s/faxboxes', rawurlencode($accountId)), ['json' => ['data' => $data->toSwitchData()]])); }
    public function update(string $accountId, string $faxBoxId, FaxBoxWriteData $data): FaxBoxSnapshot { $snapshot = $this->snapshot($this->client->request('POST', $this->path($accountId, $faxBoxId), ['json' => ['data' => $data->toSwitchData()]])); if ($snapshot->id !== $faxBoxId) throw new InvalidSwitchPayloadException('Switch fax box response id does not match the requested resource.'); return $snapshot; }
    public function delete(string $accountId, string $faxBoxId): void { $this->client->request('DELETE', $this->path($accountId, $faxBoxId)); }
    private function path(string $accountId, string $faxBoxId): string { return sprintf('accounts/%s/faxboxes/%s', rawurlencode($this->required($accountId, 'account')), rawurlencode($this->required($faxBoxId, 'fax box'))); }
    /** @param array<string, mixed> $payload */ private function snapshot(array $payload): FaxBoxSnapshot { $data = $payload['data'] ?? null; if (! is_array($data)) throw new InvalidSwitchPayloadException('Switch fax box response data must be an object.'); return new FaxBoxSnapshot($data); }
    private function required(string $id, string $name): string { if ($id === '') throw new InvalidArgumentException("Switch {$name} identifier is required."); return $id; }
}
