<?php

namespace App\Domains\Blacklists\Services;

use App\Domains\Blacklists\Models\SwitchBlacklist;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BlacklistService
{
    public function list(SwitchAccount $account, ?string $search, int $perPage): LengthAwarePaginator { return $account->blacklists()->withCount('entries')->when($search, fn ($q, $value) => $q->where('name', 'like', "%{$value}%"))->orderBy('name')->paginate($perPage); }
    public function find(SwitchAccount $account, string $id): SwitchBlacklist { return $account->blacklists()->where('id', $id)->with('entries')->firstOrFail(); }
}
