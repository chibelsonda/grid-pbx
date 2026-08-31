<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;

class RecentMissedCallsService
{
    private const RESULT_LIMIT = 5;

    public function __construct(private readonly CallAnalyticsPeriodService $periods) {}

    /** @return array<string, mixed> */
    public function get(SwitchAccount $account, string $range): array
    {
        $period = $this->periods->resolve($account, $range);
        $start = $this->periods->databaseTimestamp($period['start']);
        $end = $this->periods->databaseTimestamp($period['end']);
        $query = $account->callDetailRecords()
            ->where('started_at', '>=', $start)
            ->where('started_at', '<', $end)
            ->where('direction', 'inbound')
            ->where(function ($query): void {
                $query->whereNull('billing_seconds')
                    ->orWhere('billing_seconds', '<=', 0);
            });
        $total = (clone $query)->count();
        $dataAsOf = (clone $query)->max('last_synced_at');
        $items = $query
            ->select([
                'call_detail_record_id',
                'id',
                'caller_id_name',
                'caller_id_number',
                'callee_id_name',
                'callee_id_number',
                'started_at',
                'duration_seconds',
                'hangup_cause',
            ])
            ->orderByDesc('started_at')
            ->orderByDesc('call_detail_record_id')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (SwitchCallDetailRecord $record): array => [
                'id' => $record->id,
                'started_at' => $record->started_at->toIso8601String(),
                'caller' => [
                    'name' => $record->caller_id_name,
                    'number' => $record->caller_id_number,
                ],
                'destination' => [
                    'name' => $record->callee_id_name,
                    'number' => $record->callee_id_number,
                ],
                'duration_seconds' => (int) $record->duration_seconds,
                'hangup_cause' => $record->hangup_cause,
            ])
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
            'total' => $total,
            'items' => $items,
        ];
    }
}
