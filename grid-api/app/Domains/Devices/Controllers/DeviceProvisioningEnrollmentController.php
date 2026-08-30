<?php

namespace App\Domains\Devices\Controllers;

use App\Domains\Devices\Requests\ConfirmDeviceProvisioningEnrollmentRequest;
use App\Domains\Devices\Services\DeviceProvisioningEnrollmentService;
use App\Domains\Devices\Services\DeviceService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeviceProvisioningEnrollmentController extends Controller
{
    public function show(
        Request $request,
        string $account,
        string $device,
        SwitchAccountService $accounts,
        DeviceService $devices,
        DeviceProvisioningEnrollmentService $enrollment,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchDevice = $devices->find($switchAccount, $device);

        return ApiResponse::data($enrollment->status($switchDevice));
    }

    public function store(
        ConfirmDeviceProvisioningEnrollmentRequest $request,
        string $account,
        string $device,
        SwitchAccountService $accounts,
        DeviceService $devices,
        DeviceProvisioningEnrollmentService $enrollment,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchDevice = $devices->find($switchAccount, $device);
        Gate::authorize('update', [$switchDevice, $switchAccount]);

        return ApiResponse::data([
            'message' => 'Device enrolled for manufacturer provisioning.',
            'enrollment' => $enrollment->enroll($switchAccount, $switchDevice, $user, $request->ip()),
        ]);
    }

    public function destroy(
        ConfirmDeviceProvisioningEnrollmentRequest $request,
        string $account,
        string $device,
        SwitchAccountService $accounts,
        DeviceService $devices,
        DeviceProvisioningEnrollmentService $enrollment,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchDevice = $devices->find($switchAccount, $device);
        Gate::authorize('update', [$switchDevice, $switchAccount]);

        return ApiResponse::data([
            'message' => 'Device detached from manufacturer provisioning.',
            'enrollment' => $enrollment->detach($switchAccount, $switchDevice, $user, $request->ip()),
        ]);
    }
}
