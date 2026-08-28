<?php

namespace App\Domains\Queues\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Models\SwitchQueue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QueueService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SwitchQueue>
     */
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $account->queues()->withCount('agents')->with('musicOnHoldMedia:media_id,id,name')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['strategy'] ?? null, fn ($query, string $strategy) => $query->where('strategy', $strategy))
            ->orderBy('name')->orderBy('queue_id')->paginate($perPage)->withQueryString();
    }

    public function find(SwitchAccount $account, string $id): SwitchQueue
    {
        return $account->queues()->where('id', $id)->with([
            'musicOnHoldMedia:media_id,id,name',
            'agents.extension:extension_id,id,display_name,extension',
        ])->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function options(SwitchAccount $account): array
    {
        return [
            'agents' => $account->extensions()->whereNotNull('switch_resource_id')->orderBy('display_name')->get()
                ->map(fn ($item): array => ['id' => $item->id, 'label' => $item->display_name ?? $item->extension ?? 'Unnamed agent', 'detail' => $item->extension])->values()->all(),
            'media' => $account->media()->orderBy('name')->get()
                ->map(fn ($item): array => ['id' => $item->id, 'label' => $item->name, 'detail' => $item->content_type])->values()->all(),
        ];
    }
}
