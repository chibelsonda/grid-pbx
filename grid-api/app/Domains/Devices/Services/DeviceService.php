<?php

namespace App\Domains\Devices\Services;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeviceService
{
    /** @return LengthAwarePaginator<int, SwitchDevice> */
    public function paginate(SwitchAccount $account, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $account->devices()
            ->with('extension:extension_id,id,display_name,extension')
            ->when($search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('device_type', 'like', "%{$search}%")
                        ->orWhere('make', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('mac_address', 'like', "%{$search}%")
                        ->orWhereHas('extension', function ($query) use ($search): void {
                            $query
                                ->where('display_name', 'like', "%{$search}%")
                                ->orWhere('extension', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByRaw('name IS NULL')
            ->orderBy('name')
            ->orderBy('device_id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(SwitchAccount $account, string $deviceId): SwitchDevice
    {
        return $account->devices()
            ->where('id', $deviceId)
            ->with('extension:extension_id,id,display_name,extension')
            ->firstOrFail();
    }
}
