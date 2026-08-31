<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

class CallAnalyticsPeriodService
{
    /**
     * @return array{
     *   timezone: string,
     *   granularity: 'hour'|'day',
     *   start: CarbonImmutable,
     *   end: CarbonImmutable,
     *   buckets: array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     * }
     */
    public function resolve(SwitchAccount $account, string $range): array
    {
        $timezone = $this->timezone($account);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        [$start, $end, $granularity] = match ($range) {
            'today' => [$today, $today->addDay(), 'hour'],
            '7d' => [$today->subDays(6), $today->addDay(), 'day'],
            '30d' => [$today->subDays(29), $today->addDay(), 'day'],
            default => throw new InvalidArgumentException('Unsupported call analytics range.'),
        };
        $buckets = [];

        for ($cursor = $start; $cursor->lessThan($end); $cursor = $granularity === 'hour' ? $cursor->addHour() : $cursor->addDay()) {
            $next = $granularity === 'hour' ? $cursor->addHour() : $cursor->addDay();
            $buckets[] = ['start' => $cursor, 'end' => $next];
        }

        return compact('timezone', 'granularity', 'start', 'end', 'buckets');
    }

    public function databaseTimestamp(CarbonImmutable $timestamp): string
    {
        return $timestamp->utc()->format('Y-m-d H:i:s');
    }

    private function timezone(SwitchAccount $account): string
    {
        $timezone = $account->timezone;

        return is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : (string) config('app.timezone', 'UTC');
    }
}
