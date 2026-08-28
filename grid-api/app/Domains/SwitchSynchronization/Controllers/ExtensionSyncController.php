<?php

namespace App\Domains\SwitchSynchronization\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Resources\SyncRunResource;
use App\Domains\SwitchSynchronization\Services\StartExtensionSyncService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtensionSyncController extends Controller
{
    public function store(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        StartExtensionSyncService $sync,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);

        return (new SyncRunResource($sync->handle($switchAccount, $user)))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(
        Request $request,
        string $account,
        string $run,
        SwitchAccountService $accounts,
    ): SyncRunResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $syncRun = $switchAccount->syncRuns()->where('id', $run)->firstOrFail();

        return new SyncRunResource($syncRun);
    }
}
