<?php

namespace App\Domains\CallerIdLists\Services;

use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CallerIdListService
{
    public function list(SwitchAccount $account, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $account->callerIdLists()
            ->withCount('entries')
            ->when($search, fn ($query, $value) => $query->where('name', 'like', "%{$value}%"))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function find(SwitchAccount $account, string $id): SwitchCallerIdList
    {
        return $account->callerIdLists()
            ->where('id', $id)
            ->with('entries')
            ->firstOrFail();
    }
}
