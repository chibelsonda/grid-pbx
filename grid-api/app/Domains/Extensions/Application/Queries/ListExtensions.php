<?php

namespace App\Domains\Extensions\Application\Queries;

use App\Domains\Extensions\Infrastructure\Models\KazooExtension;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListExtensions
{
    /** @return LengthAwarePaginator<int, KazooExtension> */
    public function handle(KazooAccount $account, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $account->extensions()
            ->when($search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('display_name', 'like', "%{$search}%")
                        ->orWhere('extension', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('extension IS NULL')
            ->orderBy('extension')
            ->orderBy('display_name')
            ->paginate($perPage)
            ->withQueryString();
    }
}
