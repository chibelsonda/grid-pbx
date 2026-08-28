<?php

namespace App\Domains\Directories\Services;

use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DirectoryService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SwitchDirectory>
     */
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $account->directories()
            ->withCount('members')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')->orderBy('directory_id')->paginate($perPage)->withQueryString();
    }

    public function find(SwitchAccount $account, string $id): SwitchDirectory
    {
        return $account->directories()->where('id', $id)
            ->with(['members.extension:extension_id,id,display_name,extension', 'members.callflow:callflow_id,id,name'])
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function options(SwitchAccount $account): array
    {
        return [
            'extensions' => $account->extensions()->with(['callflows' => fn ($query) => $query->select('callflow_id', 'switch_extension_id', 'id', 'name')])
                ->orderBy('display_name')->get()->filter(fn ($extension) => $extension->callflows->isNotEmpty())
                ->map(fn ($extension): array => [
                    'id' => $extension->id, 'label' => $extension->display_name ?? $extension->extension ?? 'Unnamed extension',
                    'detail' => $extension->extension,
                ])->values()->all(),
        ];
    }
}
