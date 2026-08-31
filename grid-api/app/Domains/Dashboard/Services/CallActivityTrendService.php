<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CallActivityTrendService
{
    public function __construct(private readonly CallAnalyticsPeriodService $periods) {}

    /** @return array<string, mixed> */
    public function get(SwitchAccount $account, string $range): array
    {
        $period = $this->periods->resolve($account, $range);
        $timezone = $period['timezone'];
        $buckets = $period['buckets'];
        $rows = $this->aggregate($account, $buckets);
        $totals = [
            'total' => 0,
            'inbound' => 0,
            'outbound' => 0,
            'answered' => 0,
            'missed' => 0,
            'answer_rate' => 0.0,
            'average_duration_seconds' => 0,
        ];
        $answeredSeconds = 0;

        $series = collect($buckets)
            ->map(function (array $bucket, int $index) use ($rows, &$totals, &$answeredSeconds): array {
                $row = $rows->get($index);
                $total = (int) ($row->total ?? 0);
                $answered = (int) ($row->answered ?? 0);
                $point = [
                    'start_at' => $bucket['start']->toIso8601String(),
                    'end_at' => $bucket['end']->toIso8601String(),
                    'total' => $total,
                    'inbound' => (int) ($row->inbound ?? 0),
                    'outbound' => (int) ($row->outbound ?? 0),
                    'answered' => $answered,
                    'missed' => max(0, $total - $answered),
                ];

                foreach (['total', 'inbound', 'outbound', 'answered', 'missed'] as $metric) {
                    $totals[$metric] += $point[$metric];
                }

                $answeredSeconds += (int) ($row->answered_seconds ?? 0);

                return $point;
            })
            ->all();

        $totals['answer_rate'] = $totals['total'] === 0
            ? 0.0
            : round(($totals['answered'] / $totals['total']) * 100, 1);
        $totals['average_duration_seconds'] = $totals['answered'] === 0
            ? 0
            : (int) round($answeredSeconds / $totals['answered']);

        return [
            'range' => $range,
            'granularity' => $range === 'today' ? 'hour' : 'day',
            'timezone' => $timezone,
            'from' => $buckets[0]['start']->toIso8601String(),
            'to' => $buckets[array_key_last($buckets)]['end']->toIso8601String(),
            'totals' => $totals,
            'series' => $series,
        ];
    }

    /**
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $buckets
     * @return Collection<int, object>
     */
    private function aggregate(SwitchAccount $account, array $buckets): Collection
    {
        $conditions = [];
        $bindings = [];

        foreach ($buckets as $index => $bucket) {
            $conditions[] = 'WHEN started_at >= ? AND started_at < ? THEN ?';
            $bindings[] = $this->periods->databaseTimestamp($bucket['start']);
            $bindings[] = $this->periods->databaseTimestamp($bucket['end']);
            $bindings[] = $index;
        }

        $bucketExpression = 'CASE '.implode(' ', $conditions).' END';
        $windowStart = $buckets[0]['start'];
        $windowEnd = $buckets[array_key_last($buckets)]['end'];

        return $account->callDetailRecords()
            ->where('started_at', '>=', $this->periods->databaseTimestamp($windowStart))
            ->where('started_at', '<', $this->periods->databaseTimestamp($windowEnd))
            ->whereIn('direction', ['inbound', 'outbound'])
            ->toBase()
            ->selectRaw("{$bucketExpression} as bucket_index", $bindings)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound")
            ->selectRaw("SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound")
            ->selectRaw('SUM(CASE WHEN billing_seconds > 0 THEN 1 ELSE 0 END) as answered')
            ->selectRaw('SUM(CASE WHEN billing_seconds > 0 THEN billing_seconds ELSE 0 END) as answered_seconds')
            ->groupBy('bucket_index')
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->bucket_index);
    }
}
