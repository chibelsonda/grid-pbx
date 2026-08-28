<?php

namespace App\Domains\TemporalRouting\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use Carbon\CarbonImmutable;
use UnexpectedValueException;

class TemporalRuleProjectionService
{
    private const GREGORIAN_UNIX_OFFSET = 62167219200;

    public function __construct(private readonly RedactSensitiveSwitchData $redact) {}

    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchTemporalRule
    {
        $id = $this->string($snapshot['id'] ?? null);
        $name = $this->string($snapshot['name'] ?? null);
        $cycle = $snapshot['cycle'] ?? null;
        if ($id === null || $name === null || ! in_array($cycle, ['date', 'daily', 'weekly', 'monthly', 'yearly'], true)) {
            throw new UnexpectedValueException('Switch temporal rule response is missing required metadata.');
        }
        $switchStart = is_int($snapshot['start_date'] ?? null) ? $snapshot['start_date'] : null;
        $rule = SwitchTemporalRule::withTrashed()->firstOrNew(['switch_account_id' => $account->getKey(), 'switch_resource_id' => $id]);
        $rule->fill([
            'name' => $name, 'cycle' => $cycle, 'interval' => max(1, (int) ($snapshot['interval'] ?? 1)),
            'switch_start_date' => $switchStart, 'start_date' => $switchStart !== null && $switchStart >= self::GREGORIAN_UNIX_OFFSET ? CarbonImmutable::createFromTimestampUTC($switchStart - self::GREGORIAN_UNIX_OFFSET)->toDateString() : null,
            'time_window_start' => is_int($snapshot['time_window_start'] ?? null) ? $snapshot['time_window_start'] : null,
            'time_window_stop' => is_int($snapshot['time_window_stop'] ?? null) ? $snapshot['time_window_stop'] : null,
            'enabled' => is_bool($snapshot['enabled'] ?? null) ? $snapshot['enabled'] : null,
            'days' => is_array($snapshot['days'] ?? null) ? array_values($snapshot['days']) : [],
            'weekdays' => is_array($snapshot['wdays'] ?? null) ? array_values(array_map(fn ($day) => $day === 'wensday' ? 'wednesday' : $day, $snapshot['wdays'])) : [],
            'month' => is_int($snapshot['month'] ?? null) ? $snapshot['month'] : null,
            'ordinal' => $this->string($snapshot['ordinal'] ?? null), 'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $rule->exists ? $rule->projection_version + 1 : 1, 'switch_json' => $this->redact->handle($snapshot),
        ]);
        $rule->deleted_at = null;
        $rule->save();

        return $rule;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
