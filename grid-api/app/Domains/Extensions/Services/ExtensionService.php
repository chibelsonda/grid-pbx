<?php

namespace App\Domains\Extensions\Services;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ExtensionService
{
    /** @return LengthAwarePaginator<int, SwitchExtension> */
    public function paginate(SwitchAccount $account, ?string $search, int $perPage): LengthAwarePaginator
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

    public function find(SwitchAccount $account, string $extensionId): SwitchExtension
    {
        return $account->extensions()
            ->where('id', $extensionId)
            ->with([
                'devices' => fn ($query) => $query->orderBy('name')->orderBy('device_id'),
                'voicemailBoxes' => fn ($query) => $query->withCount('messages')->orderBy('mailbox')->orderBy('voicemail_box_id'),
                'callflows' => fn ($query) => $query->orderBy('name')->orderBy('callflow_id'),
            ])
            ->firstOrFail();
    }
}
