<?php

namespace App\Domains\CallerIdLists\Controllers;

use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\CallerIdLists\Services\StartCallerIdListSyncService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Resources\SyncRunResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CallerIdListSyncController extends Controller
{
    public function store(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        StartCallerIdListSyncService $sync,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('sync', [SwitchCallerIdList::class, $switchAccount]);

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
        Gate::authorize('viewAny', [SwitchCallerIdList::class, $switchAccount]);

        return new SyncRunResource($switchAccount->syncRuns()
            ->where('resource_type', 'caller_id_lists')
            ->where('id', $run)
            ->firstOrFail());
    }
}
