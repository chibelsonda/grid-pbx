<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use Generator;
use GridPbx\Switch\Dto\TemporalRules\TemporalRuleSnapshot;
use GridPbx\Switch\Dto\TemporalRules\TemporalRuleWriteData;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class TemporalRuleResourceClient
{
    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) throw new InvalidArgumentException('Switch page size must be greater than zero.');
    }

    /** @return Generator<int, TemporalRuleSnapshot> */
    public function allDetails(string $accountId): Generator
    {
        $accountId = $this->required($accountId, 'account');
        $cursor = null; $seen = [];
        do {
            $query = ['paginate' => 'true', 'page_size' => $this->pageSize];
            if ($cursor !== null) $query['start_key'] = $cursor;
            $payload = $this->client->request('GET', sprintf('accounts/%s/temporal_rules', rawurlencode($accountId)), ['query' => $query]);
            $data = $payload['data'] ?? null;
            if (! is_array($data)) throw new InvalidSwitchPayloadException('Switch temporal rule collection response data must be an array.');
            foreach ($data as $summary) {
                $id = is_array($summary) ? ($summary['id'] ?? null) : null;
                if (! is_string($id) || $id === '') throw new InvalidSwitchPayloadException('Switch temporal rule collection entry must contain an id.');
                yield $this->get($accountId, $id);
            }
            $next = $payload['next_start_key'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;
            if ($cursor !== null && isset($seen[$cursor])) throw new InvalidSwitchPayloadException('Switch temporal rule pagination returned a repeated cursor.');
            if ($cursor !== null) $seen[$cursor] = true;
        } while ($cursor !== null);
    }

    public function get(string $accountId, string $ruleId): TemporalRuleSnapshot { return $this->snapshot($this->client->request('GET', $this->path($accountId, $ruleId))); }
    public function create(string $accountId, TemporalRuleWriteData $rule): TemporalRuleSnapshot
    {
        $accountId = $this->required($accountId, 'account');
        return $this->snapshot($this->client->request('PUT', sprintf('accounts/%s/temporal_rules', rawurlencode($accountId)), ['json' => ['data' => $rule->toSwitchData()]]));
    }
    public function update(string $accountId, string $ruleId, TemporalRuleWriteData $rule): TemporalRuleSnapshot
    {
        $snapshot = $this->snapshot($this->client->request('POST', $this->path($accountId, $ruleId), ['json' => ['data' => $rule->toSwitchData()]]));
        if ($snapshot->id !== $ruleId) throw new InvalidSwitchPayloadException('Switch temporal rule response id does not match the requested resource.');
        return $snapshot;
    }
    public function delete(string $accountId, string $ruleId): void { $this->client->request('DELETE', $this->path($accountId, $ruleId)); }
    private function path(string $accountId, string $ruleId): string { return sprintf('accounts/%s/temporal_rules/%s', rawurlencode($this->required($accountId, 'account')), rawurlencode($this->required($ruleId, 'rule'))); }
    /** @param array<string, mixed> $payload */ private function snapshot(array $payload): TemporalRuleSnapshot
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data)) throw new InvalidSwitchPayloadException('Switch temporal rule response data must be an object.');
        return new TemporalRuleSnapshot($data);
    }
    private function required(string $id, string $name): string { if ($id === '') throw new InvalidArgumentException("Switch {$name} identifier is required."); return $id; }
}
