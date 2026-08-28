<?php

namespace App\Domains\PhoneNumbers\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PhoneNumberService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SwitchPhoneNumber>
     */
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $account->phoneNumbers()
            ->with('assignedCallflow:callflow_id,id,name,numbers')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('number', 'like', "%{$search}%")
                        ->orWhere('cnam_display_name', 'like', "%{$search}%")
                        ->orWhere('carrier_name', 'like', "%{$search}%")
                        ->orWhereHas('assignedCallflow', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['state'] ?? null, fn ($query, string $state) => $query->where('state', $state))
            ->when(($filters['assignment'] ?? null) === 'assigned', fn ($query) => $query->whereNotNull('assigned_callflow_id'))
            ->when(($filters['assignment'] ?? null) === 'unassigned', fn ($query) => $query->whereNull('assigned_callflow_id'))
            ->when($filters['feature'] ?? null, fn ($query, string $feature) => $query->whereJsonContains('features', $feature))
            ->orderBy('number')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(SwitchAccount $account, string $phoneNumberId): SwitchPhoneNumber
    {
        return $account->phoneNumbers()
            ->where('id', $phoneNumberId)
            ->with('assignedCallflow:callflow_id,id,name,numbers')
            ->firstOrFail();
    }
}
