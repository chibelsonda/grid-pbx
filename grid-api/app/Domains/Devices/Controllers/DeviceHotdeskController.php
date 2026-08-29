<?php

namespace App\Domains\Devices\Controllers;

use App\Domains\Devices\Services\DeviceHotdeskService;
use App\Domains\Devices\Services\DeviceService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeviceHotdeskController extends Controller
{
    public function index(
        Request $request,
        string $account,
        string $device,
        SwitchAccountService $accounts,
        DeviceService $devices,
        DeviceHotdeskService $hotdesk,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchDevice = $devices->find($switchAccount, $device);

        return response()->json(['data' => $hotdesk->memberships($switchAccount, $switchDevice)]);
    }

    public function store(
        Request $request,
        string $account,
        string $device,
        string $extension,
        SwitchAccountService $accounts,
        DeviceService $devices,
        DeviceHotdeskService $hotdesk,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchDevice = $devices->find($switchAccount, $device);
        Gate::authorize('update', [$switchDevice, $switchAccount]);
        $switchExtension = $switchAccount->extensions()
            ->where('id', $extension)
            ->whereNotNull('switch_resource_id')
            ->firstOrFail();

        return response()->json(['data' => $hotdesk->signIn(
            $switchAccount,
            $switchDevice,
            $switchExtension,
            $user,
            $request->ip(),
        )]);
    }

    public function destroy(
        Request $request,
        string $account,
        string $device,
        string $extension,
        SwitchAccountService $accounts,
        DeviceService $devices,
        DeviceHotdeskService $hotdesk,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchDevice = $devices->find($switchAccount, $device);
        Gate::authorize('update', [$switchDevice, $switchAccount]);
        $switchExtension = $switchAccount->extensions()
            ->where('id', $extension)
            ->whereNotNull('switch_resource_id')
            ->firstOrFail();

        return response()->json(['data' => $hotdesk->signOut(
            $switchAccount,
            $switchDevice,
            $switchExtension,
            $user,
            $request->ip(),
        )]);
    }
}
