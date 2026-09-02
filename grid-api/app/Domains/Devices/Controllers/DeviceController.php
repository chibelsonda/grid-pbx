<?php

namespace App\Domains\Devices\Controllers;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Devices\Requests\ListDevicesRequest;
use App\Domains\Devices\Requests\SaveDeviceRequest;
use App\Domains\Devices\Resources\DeviceResource;
use App\Domains\Devices\Services\DeviceMutationService;
use App\Domains\Devices\Services\DeviceOptionsService;
use App\Domains\Devices\Services\DeviceService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DeviceController extends Controller
{
    public function index(
        ListDevicesRequest $request,
        string $account,
        SwitchAccountService $accounts,
        DeviceService $devices,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $validated = $request->validated();
        $checkpoint = SyncCheckpoint::query()
            ->where('switch_account_id', $switchAccount->getKey())
            ->where('resource_type', 'extensions')
            ->first();

        return DeviceResource::collection($devices->paginate(
            $switchAccount,
            $validated['search'] ?? null,
            (int) ($validated['per_page'] ?? 25),
        ))->additional([
            'meta' => [
                'sync' => [
                    'status' => $checkpoint?->status->value ?? 'stale',
                    'last_successful_at' => $checkpoint?->last_successful_at?->toIso8601String(),
                    'error_message' => $checkpoint?->publicErrorMessage(),
                ],
            ],
        ]);
    }

    public function show(
        Request $request,
        string $account,
        string $device,
        SwitchAccountService $accounts,
        DeviceService $devices,
    ): DeviceResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);

        return new DeviceResource($devices->find($switchAccount, $device));
    }

    public function options(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        DeviceOptionsService $options,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);

        return ApiResponse::data($options->get($switchAccount));
    }

    public function store(
        SaveDeviceRequest $request,
        string $account,
        SwitchAccountService $accounts,
        DeviceMutationService $mutations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchDevice::class, $switchAccount]);

        return (new DeviceResource($mutations->create(
            $switchAccount,
            $user,
            $request->validated(),
            $request->ip(),
        )))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        SaveDeviceRequest $request,
        string $account,
        string $device,
        SwitchAccountService $accounts,
        DeviceService $devices,
        DeviceMutationService $mutations,
    ): DeviceResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchDevice = $devices->find($switchAccount, $device);
        Gate::authorize('update', [$switchDevice, $switchAccount]);

        return new DeviceResource($mutations->update(
            $switchAccount,
            $switchDevice,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function destroy(
        Request $request,
        string $account,
        string $device,
        SwitchAccountService $accounts,
        DeviceService $devices,
        DeviceMutationService $mutations,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchDevice = $devices->find($switchAccount, $device);
        Gate::authorize('delete', [$switchDevice, $switchAccount]);
        $mutations->delete(
            $switchAccount,
            $switchDevice,
            $user,
            $request->ip(),
        );

        return ApiResponse::noContent();
    }
}
