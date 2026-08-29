<?php

namespace App\Domains\TemporalRouting\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleSetGateway;
use Generator;
use GridPbx\Switch\Domains\TemporalRuleSets\Dto\TemporalRuleSetWriteData;
use GridPbx\Switch\Domains\TemporalRuleSets\TemporalRuleSetResourceClient;

class CrossbarSwitchTemporalRuleSetGateway implements SwitchTemporalRuleSetGateway
{
    public function __construct(private readonly TemporalRuleSetResourceClient $sets) {}

    public function all(SwitchAccount $account): Generator
    {
        foreach ($this->sets->allDetails($account->switch_account_id) as $set) {
            yield $set->toArray();
        }
    }

    public function create(SwitchAccount $account, array $data): array
    {
        return $this->sets->create($account->switch_account_id, new TemporalRuleSetWriteData((string) $data['name'], $data['switch_rule_ids'], array_values($data['flags'] ?? [])))->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->sets->update($account->switch_account_id, $resourceId, new TemporalRuleSetWriteData((string) $data['name'], $data['switch_rule_ids'], array_values($data['flags'] ?? [])))->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->sets->delete($account->switch_account_id, $resourceId);
    }
}
