<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CallflowService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SwitchCallflow>
     */
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $account->callflows()
            ->with([
                'extension:extension_id,id,display_name,extension',
                'phoneNumbers:phone_number_id,id,assigned_callflow_id,number,state',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('numbers', 'like', "%{$search}%")
                        ->orWhere('patterns', 'like', "%{$search}%")
                        ->orWhere('feature_code_name', 'like', "%{$search}%")
                        ->orWhere('feature_code_number', 'like', "%{$search}%");
                });
            })
            ->when($filters['module'] ?? null, fn ($query, string $module) => $query->whereJsonContains('modules', $module))
            ->when($filters['type'] ?? null, function ($query, string $type): void {
                match ($type) {
                    'extension' => $query->whereNotNull('switch_extension_id'),
                    'phone_number' => $query->whereHas('phoneNumbers'),
                    'feature_code' => $query->where('is_feature_code', true),
                    'pattern' => $query->whereNotNull('patterns')->where('patterns', '!=', '[]'),
                    'unassigned' => $query
                        ->whereNull('switch_extension_id')
                        ->whereDoesntHave('phoneNumbers')
                        ->where('is_feature_code', false),
                };
            })
            ->orderByRaw('name IS NULL')
            ->orderBy('name')
            ->orderBy('callflow_id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(SwitchAccount $account, string $callflowId): SwitchCallflow
    {
        return $account->callflows()
            ->where('id', $callflowId)
            ->with([
                'extension:extension_id,id,display_name,extension',
                'phoneNumbers:phone_number_id,id,assigned_callflow_id,number,state',
            ])
            ->firstOrFail();
    }
}
