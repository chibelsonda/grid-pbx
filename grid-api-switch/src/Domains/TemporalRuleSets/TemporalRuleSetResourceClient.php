<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\TemporalRuleSets;

use Generator;
use GridPbx\Switch\Domains\TemporalRuleSets\Dto\TemporalRuleSetSnapshot;
use GridPbx\Switch\Domains\TemporalRuleSets\Dto\TemporalRuleSetWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class TemporalRuleSetResourceClient
{
    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Switch page size must be greater than zero.');
        }
    }

    /** @return Generator<int, TemporalRuleSetSnapshot> */
    public function allDetails(string $accountId): Generator
    {
        $accountId = $this->required($accountId, 'account');
        $cursor = null;
        $seen = [];
        do {
            $query = ['paginate' => 'true', 'page_size' => $this->pageSize];
            if ($cursor !== null) {
                $query['start_key'] = $cursor;
            }
            $payload = $this->client->request('GET', sprintf('accounts/%s/temporal_rules_sets', rawurlencode($accountId)), ['query' => $query]);
            $data = $payload['data'] ?? null;
            if (! is_array($data)) {
                throw new InvalidSwitchPayloadException('Switch temporal rule set collection response data must be an array.');
            }
            foreach ($data as $summary) {
                $id = is_array($summary) ? ($summary['id'] ?? null) : null;
                if (! is_string($id) || $id === '') {
                    throw new InvalidSwitchPayloadException('Switch temporal rule set collection entry must contain an id.');
                }
                yield $this->get($accountId, $id);
            }
            $next = $payload['next_start_key'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;
            if ($cursor !== null && isset($seen[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch temporal rule set pagination returned a repeated cursor.');
            }
            if ($cursor !== null) {
                $seen[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    public function get(string $accountId, string $setId): TemporalRuleSetSnapshot
    {
        return $this->snapshot($this->client->request('GET', $this->path($accountId, $setId)));
    }

    public function create(string $accountId, TemporalRuleSetWriteData $set): TemporalRuleSetSnapshot
    {
        $accountId = $this->required($accountId, 'account');

        return $this->snapshot($this->client->request('PUT', sprintf('accounts/%s/temporal_rules_sets', rawurlencode($accountId)), ['json' => ['data' => $set->toSwitchData()]]));
    }

    public function update(string $accountId, string $setId, TemporalRuleSetWriteData $set): TemporalRuleSetSnapshot
    {
        $snapshot = $this->snapshot($this->client->request('POST', $this->path($accountId, $setId), ['json' => ['data' => $set->toSwitchData()]]));
        if ($snapshot->id !== $setId) {
            throw new InvalidSwitchPayloadException('Switch temporal rule set response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function delete(string $accountId, string $setId): void
    {
        $this->client->request('DELETE', $this->path($accountId, $setId));
    }

    private function path(string $accountId, string $setId): string
    {
        return sprintf('accounts/%s/temporal_rules_sets/%s', rawurlencode($this->required($accountId, 'account')), rawurlencode($this->required($setId, 'rule set')));
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): TemporalRuleSetSnapshot
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch temporal rule set response data must be an object.');
        }

        return new TemporalRuleSetSnapshot($data);
    }

    private function required(string $id, string $name): string
    {
        if ($id === '') {
            throw new InvalidArgumentException("Switch {$name} identifier is required.");
        }

        return $id;
    }
}
