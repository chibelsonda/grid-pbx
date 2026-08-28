<?php

namespace App\Domains\Menus\Services;

use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MenuService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SwitchMenu>
     */
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $account->menus()->with($this->mediaRelations())
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')->orderBy('menu_id')->paginate($perPage)->withQueryString();
    }

    public function find(SwitchAccount $account, string $id): SwitchMenu
    {
        return $account->menus()->where('id', $id)->with($this->mediaRelations())->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function options(SwitchAccount $account): array
    {
        return ['media' => $account->media()->orderBy('name')->get()->map(fn ($item): array => ['id' => $item->id, 'label' => $item->name, 'detail' => $item->content_type])->values()->all()];
    }

    /** @return list<string> */
    private function mediaRelations(): array
    {
        return ['greetingMedia:media_id,id,name', 'invalidMedia:media_id,id,name', 'transferMedia:media_id,id,name', 'exitMedia:media_id,id,name'];
    }
}
