<?php

namespace App\Domains\Groups\Services;

use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GroupService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SwitchGroup>
     */
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $account->groups()->withCount('members')->with('musicOnHoldMedia:media_id,id,name')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')->orderBy('group_id')->paginate($perPage)->withQueryString();
    }

    public function find(SwitchAccount $account, string $id): SwitchGroup
    {
        return $account->groups()->where('id', $id)->with([
            'musicOnHoldMedia:media_id,id,name',
            'members.extension:extension_id,id,display_name,extension',
            'members.device:device_id,id,name,device_type',
            'members.nestedGroup:group_id,id,name',
        ])->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function options(SwitchAccount $account): array
    {
        return [
            'users' => $account->extensions()->orderBy('display_name')->get()->map(fn ($item): array => ['id' => $item->id, 'label' => $item->display_name ?? $item->extension ?? 'Unnamed extension', 'detail' => $item->extension])->values()->all(),
            'devices' => $account->devices()->orderBy('name')->get()->map(fn ($item): array => ['id' => $item->id, 'label' => $item->name ?? 'Unnamed device', 'detail' => $item->device_type])->values()->all(),
            'groups' => $account->groups()->orderBy('name')->get()->map(fn ($item): array => ['id' => $item->id, 'label' => $item->name, 'detail' => 'Nested group'])->values()->all(),
            'media' => $account->media()->orderBy('name')->get()->map(fn ($item): array => ['id' => $item->id, 'label' => $item->name, 'detail' => $item->content_type])->values()->all(),
        ];
    }
}
