<?php

namespace App\Domains\Media\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Media\Services\StartMediaSyncService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Resources\SyncRunResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MediaSyncController extends Controller
{
    public function store(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        StartMediaSyncService $sync,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('sync', [SwitchMedia::class, $switchAccount]);

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
        Gate::authorize('viewAny', [SwitchMedia::class, $switchAccount]);
        $syncRun = $switchAccount->syncRuns()
            ->where('resource_type', 'media')
            ->where('id', $run)
            ->firstOrFail();

        return new SyncRunResource($syncRun);
    }
}
