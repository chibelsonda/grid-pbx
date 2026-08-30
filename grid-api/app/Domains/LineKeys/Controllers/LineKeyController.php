<?php

namespace App\Domains\LineKeys\Controllers;

use App\Domains\Devices\Services\DeviceService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\LineKeys\Requests\ListLineKeysRequest;
use App\Domains\LineKeys\Requests\SaveLineKeysRequest;
use App\Domains\LineKeys\Resources\LineKeyDeviceResource;
use App\Domains\LineKeys\Services\LineKeyMutationService;
use App\Domains\LineKeys\Services\LineKeyService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class LineKeyController extends Controller
{
    public function index(ListLineKeysRequest $request, string $account, SwitchAccountService $accounts, LineKeyService $lineKeys): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);

        return LineKeyDeviceResource::collection($lineKeys->devices($switchAccount, $request->validated('search')));
    }

    public function preview(Request $request, string $account, string $device, SwitchAccountService $accounts, DeviceService $devices, LineKeyService $lineKeys): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchDevice = $devices->find($switchAccount, $device);
        $preview = $lineKeys->preview($switchDevice);

        return ApiResponse::data([
            'device' => (new LineKeyDeviceResource($preview['device']))->resolve($request),
            'capability' => $preview['capability'],
            'value_choices' => $preview['value_choices'],
            'payload_preview' => $preview['payload_preview'],
        ]);
    }

    public function update(SaveLineKeysRequest $request, string $account, string $device, SwitchAccountService $accounts, DeviceService $devices, LineKeyMutationService $mutations): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchDevice = $devices->find($switchAccount, $device);
        Gate::authorize('update', [$switchDevice, $switchAccount]);
        $updated = $mutations->update($switchAccount, $switchDevice, $user, $request->validated('line_keys'), $request->ip());

        return ApiResponse::data([
            'device' => (new LineKeyDeviceResource($updated))->resolve($request),
        ]);
    }
}
