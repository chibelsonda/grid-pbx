<?php

namespace App\Domains\CallDetailRecords\Services;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CallDetailRecordService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SwitchCallDetailRecord>
     */
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $account->callDetailRecords()
            ->with('extension:extension_id,id,display_name,extension')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('call_id', 'like', "%{$search}%")
                        ->orWhere('interaction_id', 'like', "%{$search}%")
                        ->orWhere('caller_id_name', 'like', "%{$search}%")
                        ->orWhere('caller_id_number', 'like', "%{$search}%")
                        ->orWhere('callee_id_name', 'like', "%{$search}%")
                        ->orWhere('callee_id_number', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['direction'] ?? null,
                fn ($query, string $direction) => $query->where('direction', $direction),
            )
            ->when(
                ($filters['outcome'] ?? null) === 'answered',
                fn ($query) => $query->where('billing_seconds', '>', 0),
            )
            ->when(
                ($filters['outcome'] ?? null) === 'unanswered',
                fn ($query) => $query->where('billing_seconds', 0),
            )
            ->when(
                $filters['hangup_cause'] ?? null,
                fn ($query, string $cause) => $query->where('hangup_cause', $cause),
            )
            ->when($filters['started_from'] ?? null, fn ($query, string $from) => $query->where(
                'started_at',
                '>=',
                CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC'),
            ))
            ->when($filters['started_to'] ?? null, fn ($query, string $to) => $query->where(
                'started_at',
                '<',
                CarbonImmutable::createFromFormat('!Y-m-d', $to, 'UTC')->addDay(),
            ))
            ->when(
                isset($filters['duration_min']),
                fn ($query) => $query->where('duration_seconds', '>=', (int) $filters['duration_min']),
            )
            ->when(
                isset($filters['duration_max']),
                fn ($query) => $query->where('duration_seconds', '<=', (int) $filters['duration_max']),
            )
            ->orderByDesc('started_at')
            ->orderByDesc('call_detail_record_id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(SwitchAccount $account, string $recordId): SwitchCallDetailRecord
    {
        return $account->callDetailRecords()
            ->where('id', $recordId)
            ->with('extension:extension_id,id,display_name,extension')
            ->firstOrFail();
    }
}
