<?php

namespace App\Domains\Conferences\Services;

use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ConferenceService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SwitchConference>
     */
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $account->conferences()->with(['owner', 'numbers', 'switchAccount.media'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhereHas('numbers', fn ($numbers) => $numbers->where('number', 'like', "%{$search}%"))))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('active_members', '>', 0))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('active_members', 0)->where('is_locked', false))
            ->when(($filters['status'] ?? null) === 'locked', fn ($query) => $query->where('is_locked', true))
            ->orderBy('name')->orderBy('conference_id')->paginate($perPage)->withQueryString();
    }

    public function find(SwitchAccount $account, string $id): SwitchConference
    {
        return $account->conferences()->where('id', $id)->with(['owner', 'numbers', 'switchAccount.media'])->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function options(SwitchAccount $account): array
    {
        $mediaOption = fn ($item): array => [
            'id' => $item->id,
            'label' => $item->name,
            'detail' => $item->description,
        ];

        return [
            'owners' => $account->extensions()->orderBy('display_name')->get()->map(fn ($item): array => [
                'id' => $item->id,
                'label' => $item->display_name ?? $item->extension ?? 'Unnamed user',
                'detail' => $item->extension,
            ])->values()->all(),
            'media' => $account->media()->orderBy('name')->get()->map($mediaOption)->values()->all(),
            'playable_media' => $account->media()
                ->where('streamable', true)
                ->where('content_type', 'like', 'audio/%')
                ->orderBy('name')
                ->get()
                ->map($mediaOption)
                ->values()
                ->all(),
        ];
    }
}
