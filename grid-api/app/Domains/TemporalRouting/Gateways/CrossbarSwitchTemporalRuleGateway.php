<?php

namespace App\Domains\TemporalRouting\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleGateway;
use Carbon\CarbonImmutable;
use Generator;
use GridPbx\Switch\Dto\TemporalRules\TemporalRuleWriteData;
use GridPbx\Switch\Resources\TemporalRuleResourceClient;

class CrossbarSwitchTemporalRuleGateway implements SwitchTemporalRuleGateway
{
    private const GREGORIAN_UNIX_OFFSET = 62167219200;

    public function __construct(private readonly TemporalRuleResourceClient $rules) {}

    public function all(SwitchAccount $account): Generator
    {
        foreach ($this->rules->allDetails($account->switch_account_id) as $rule) {
            yield $rule->toArray();
        }
    }

    public function create(SwitchAccount $account, array $data): array
    {
        return $this->rules->create($account->switch_account_id, $this->writeData($data))->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->rules->update($account->switch_account_id, $resourceId, $this->writeData($data))->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->rules->delete($account->switch_account_id, $resourceId);
    }

    public function setOverride(SwitchAccount $account, string $resourceId, ?bool $enabled): array
    {
        return $this->rules->setOverride($account->switch_account_id, $resourceId, $enabled)->toArray();
    }

    /** @param array<string, mixed> $data */
    private function writeData(array $data): TemporalRuleWriteData
    {
        $startDate = empty($data['start_date']) ? null : self::GREGORIAN_UNIX_OFFSET + CarbonImmutable::parse((string) $data['start_date'], 'UTC')->startOfDay()->timestamp;

        return new TemporalRuleWriteData(
            name: (string) $data['name'], cycle: (string) $data['cycle'], interval: (int) $data['interval'],
            startDate: $startDate, timeWindowStart: $data['time_window_start'] ?? null,
            timeWindowStop: $data['time_window_stop'] ?? null, enabled: $data['enabled'] ?? null,
            days: array_values($data['days'] ?? []), weekdays: array_values($data['weekdays'] ?? []),
            month: $data['month'] ?? null, ordinal: $data['ordinal'] ?? null,
        );
    }
}
