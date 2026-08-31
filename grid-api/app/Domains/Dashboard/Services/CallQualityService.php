<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;

class CallQualityService
{
    private const POTENTIAL_ABANDONMENT_SECONDS = 15;

    public function __construct(private readonly CallAnalyticsPeriodService $periods) {}

    /** @return array<string, mixed> */
    public function get(SwitchAccount $account, string $range): array
    {
        $period = $this->periods->resolve($account, $range);
        $query = $account->callDetailRecords()
            ->where('started_at', '>=', $this->periods->databaseTimestamp($period['start']))
            ->where('started_at', '<', $this->periods->databaseTimestamp($period['end']));
        $dataAsOf = (clone $query)->max('last_synced_at');
        $metrics = $query
            ->toBase()
            ->selectRaw('COUNT(*) AS total_calls')
            ->selectRaw("SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) AS inbound_calls")
            ->selectRaw('SUM(CASE WHEN billing_seconds > 0 THEN 1 ELSE 0 END) AS answered_calls')
            ->selectRaw("SUM(CASE WHEN direction = 'inbound' AND billing_seconds > 0 THEN 1 ELSE 0 END) AS answered_inbound")
            ->selectRaw("SUM(CASE WHEN direction = 'inbound' AND billing_seconds = 0 THEN 1 ELSE 0 END) AS unanswered_inbound")
            ->selectRaw(
                "SUM(CASE WHEN direction = 'inbound' AND billing_seconds = 0 AND duration_seconds <= ? THEN 1 ELSE 0 END) AS potential_abandonments",
                [self::POTENTIAL_ABANDONMENT_SECONDS],
            )
            ->selectRaw("SUM(CASE WHEN direction = 'inbound' AND billing_seconds > 0 THEN CASE WHEN duration_seconds > billing_seconds THEN duration_seconds - billing_seconds ELSE 0 END ELSE 0 END) AS pre_answer_seconds")
            ->selectRaw('SUM(CASE WHEN duration_seconds < 30 THEN 1 ELSE 0 END) AS duration_under_30')
            ->selectRaw('SUM(CASE WHEN duration_seconds >= 30 AND duration_seconds < 60 THEN 1 ELSE 0 END) AS duration_30_to_59')
            ->selectRaw('SUM(CASE WHEN duration_seconds >= 60 AND duration_seconds < 300 THEN 1 ELSE 0 END) AS duration_60_to_299')
            ->selectRaw('SUM(CASE WHEN duration_seconds >= 300 AND duration_seconds < 900 THEN 1 ELSE 0 END) AS duration_300_to_899')
            ->selectRaw('SUM(CASE WHEN duration_seconds >= 900 THEN 1 ELSE 0 END) AS duration_900_plus')
            ->first();
        $total = (int) ($metrics->total_calls ?? 0);
        $inbound = (int) ($metrics->inbound_calls ?? 0);
        $answeredInbound = (int) ($metrics->answered_inbound ?? 0);
        $potentialAbandonments = (int) ($metrics->potential_abandonments ?? 0);

        return [
            'generated_at' => now()->toIso8601String(),
            'data_as_of' => is_string($dataAsOf) && $dataAsOf !== ''
                ? CarbonImmutable::parse($dataAsOf)->toIso8601String()
                : null,
            'range' => $range,
            'timezone' => $period['timezone'],
            'from' => $period['start']->toIso8601String(),
            'to' => $period['end']->toIso8601String(),
            'answer_time' => [
                'answered_inbound_calls' => $answeredInbound,
                'average_pre_answer_seconds' => $answeredInbound === 0
                    ? null
                    : (int) round(((int) $metrics->pre_answer_seconds) / $answeredInbound),
                'disclosure' => 'Derived from total duration minus billed duration for answered inbound calls.',
            ],
            'potential_abandonment' => [
                'threshold_seconds' => self::POTENTIAL_ABANDONMENT_SECONDS,
                'inbound_calls' => $inbound,
                'unanswered_inbound_calls' => (int) ($metrics->unanswered_inbound ?? 0),
                'potential_calls' => $potentialAbandonments,
                'rate' => $inbound === 0
                    ? 0.0
                    : round(($potentialAbandonments / $inbound) * 100, 1),
                'disclosure' => 'Heuristic: inbound calls unanswered within 15 seconds; not a definitive queue-abandonment event.',
            ],
            'duration_distribution' => [
                'total_calls' => $total,
                'bands' => [
                    $this->durationBand('under_30', 'Under 30 sec', 0, 29, (int) ($metrics->duration_under_30 ?? 0), $total),
                    $this->durationBand('30_to_59', '30–59 sec', 30, 59, (int) ($metrics->duration_30_to_59 ?? 0), $total),
                    $this->durationBand('1_to_5_minutes', '1–5 min', 60, 299, (int) ($metrics->duration_60_to_299 ?? 0), $total),
                    $this->durationBand('5_to_15_minutes', '5–15 min', 300, 899, (int) ($metrics->duration_300_to_899 ?? 0), $total),
                    $this->durationBand('15_minutes_plus', '15+ min', 900, null, (int) ($metrics->duration_900_plus ?? 0), $total),
                ],
            ],
        ];
    }

    /** @return array<string, int|float|string|null> */
    private function durationBand(
        string $key,
        string $label,
        int $minimum,
        ?int $maximum,
        int $count,
        int $total,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'minimum_seconds' => $minimum,
            'maximum_seconds' => $maximum,
            'count' => $count,
            'percentage' => $total === 0 ? 0.0 : round(($count / $total) * 100, 1),
        ];
    }
}
