<?php

namespace App\Domains\Devices\Controllers;

use App\Domains\Devices\Requests\SyncDeviceProvisioningRequest;
use App\Domains\Devices\Services\DeviceProvisioningControlService;
use App\Domains\Devices\Services\DeviceService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DeviceProvisioningController extends Controller
{
    public function __invoke(
        SyncDeviceProvisioningRequest $request,
        string $account,
        string $device,
        SwitchAccountService $accounts,
        DeviceService $devices,
        DeviceProvisioningControlService $controls,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchDevice = $devices->find($switchAccount, $device);
        Gate::authorize('update', [$switchDevice, $switchAccount]);
        $command = $request->command();
        $reboot = $command === 'reprovision';

        $controls->sync($switchAccount, $switchDevice, $user, $reboot, $request->ip());

        return ApiResponse::data([
            'message' => $reboot
                ? 'Switch accepted the device reprovision request.'
                : 'Switch accepted the device synchronization request.',
            'command' => $command,
            'reboot' => $reboot,
        ]);
    }
}
