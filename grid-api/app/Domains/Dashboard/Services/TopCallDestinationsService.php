<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;

class TopCallDestinationsService
{
    private const RESULT_LIMIT = 5;

    public function __construct(private readonly CallAnalyticsPeriodService $periods) {}

    /** @return array<string, mixed> */
    public function get(SwitchAccount $account, string $range): array
    {
        $period = $this->periods->resolve($account, $range);
        $query = $account->callDetailRecords()
            ->where('started_at', '>=', $this->periods->databaseTimestamp($period['start']))
            ->where('started_at', '<', $this->periods->databaseTimestamp($period['end']))
            ->whereIn('direction', ['inbound', 'outbound']);
        $dataAsOf = (clone $query)->max('last_synced_at');
        $destinations = $query
            ->where(function ($query): void {
                $query->whereNotNull('callee_id_name')
                    ->orWhereNotNull('callee_id_number');
            })
            ->toBase()
            ->select(['callee_id_name', 'callee_id_number'])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) AS inbound")
            ->selectRaw("SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) AS outbound")
            ->selectRaw('SUM(CASE WHEN billing_seconds > 0 THEN 1 ELSE 0 END) AS answered')
            ->groupBy(['callee_id_name', 'callee_id_number'])
            ->orderByDesc('total')
            ->orderByDesc('answered')
            ->orderBy('callee_id_number')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(function (object $destination): array {
                $total = (int) $destination->total;
                $answered = (int) $destination->answered;

                return [
                    'name' => $destination->callee_id_name,
                    'number' => $destination->callee_id_number,
                    'total' => $total,
                    'inbound' => (int) $destination->inbound,
                    'outbound' => (int) $destination->outbound,
                    'answered' => $answered,
                    'unanswered' => max(0, $total - $answered),
                ];
            })
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'data_as_of' => is_string($dataAsOf) && $dataAsOf !== ''
                ? CarbonImmutable::parse($dataAsOf)->toIso8601String()
                : null,
            'range' => $range,
            'timezone' => $period['timezone'],
            'from' => $period['start']->toIso8601String(),
            'to' => $period['end']->toIso8601String(),
            'destinations' => $destinations,
        ];
    }
}
