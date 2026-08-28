<?php

namespace App\Domains\Faxes\Services;

use App\Domains\Faxes\Models\SwitchFax;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FaxService
{
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $account->faxes()->with(['faxBox', 'owner'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($nested) => $nested->where('from_number', 'like', "%{$search}%")->orWhere('to_number', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%")))
            ->when($filters['folder'] ?? null, fn ($query, string $folder) => $query->where('folder', $folder))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['fax_box_id'] ?? null, fn ($query, string $id) => $query->whereHas('faxBox', fn ($box) => $box->where('id', $id)))
            ->when($filters['created_from'] ?? null, fn ($query, string $date) => $query->where('switch_created_at', '>=', $date.' 00:00:00'))
            ->when($filters['created_to'] ?? null, fn ($query, string $date) => $query->where('switch_created_at', '<=', $date.' 23:59:59'))
            ->orderByDesc('switch_created_at')->orderByDesc('fax_id')->paginate($perPage)->withQueryString();
    }
    public function find(SwitchAccount $account, string $id): SwitchFax { return $account->faxes()->where('id', $id)->with(['faxBox', 'owner'])->firstOrFail(); }
}
