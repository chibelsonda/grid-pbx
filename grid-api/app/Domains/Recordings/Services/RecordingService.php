<?php

namespace App\Domains\Recordings\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Recordings\Models\SwitchRecording;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RecordingService
{
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $account->recordings()->with(['extension:extension_id,id,display_name,extension', 'callDetailRecord:call_detail_record_id,id'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('call_id', 'like', "%{$search}%")->orWhere('interaction_id', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('caller_id_name', 'like', "%{$search}%")->orWhere('caller_id_number', 'like', "%{$search}%")->orWhere('callee_id_name', 'like', "%{$search}%")->orWhere('callee_id_number', 'like', "%{$search}%")))
            ->when($filters['direction'] ?? null, fn ($query, $value) => $query->where('direction', $value))
            ->when($filters['started_from'] ?? null, fn ($query, $value) => $query->where('started_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC')))
            ->when($filters['started_to'] ?? null, fn ($query, $value) => $query->where('started_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC')->addDay()))
            ->when(isset($filters['duration_min']), fn ($query) => $query->where('duration_seconds', '>=', (int) $filters['duration_min']))
            ->when(isset($filters['duration_max']), fn ($query) => $query->where('duration_seconds', '<=', (int) $filters['duration_max']))
            ->when(isset($filters['has_audio']), fn ($query) => $query->where('has_audio', (bool) $filters['has_audio']))
            ->orderByDesc('started_at')->orderByDesc('recording_id')->paginate($perPage)->withQueryString();
    }
    public function find(SwitchAccount $account, string $id): SwitchRecording { return $account->recordings()->where('id', $id)->with(['extension:extension_id,id,display_name,extension', 'callDetailRecord:call_detail_record_id,id'])->firstOrFail(); }
}
