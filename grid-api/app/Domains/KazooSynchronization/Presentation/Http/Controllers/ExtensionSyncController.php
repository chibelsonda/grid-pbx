<?php

namespace App\Domains\KazooSynchronization\Presentation\Http\Controllers;

use App\Domains\IdentityAccess\Infrastructure\Models\User;
use App\Domains\KazooSynchronization\Application\Actions\StartExtensionSync;
use App\Domains\KazooSynchronization\Presentation\Http\Resources\SyncRunResource;
use App\Domains\Organizations\Application\Queries\FindAccessibleKazooAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtensionSyncController extends Controller
{
    public function store(
        Request $request,
        string $account,
        FindAccessibleKazooAccount $findAccount,
        StartExtensionSync $startSync,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $kazooAccount = $findAccount->handle($user, $account);

        return (new SyncRunResource($startSync->handle($kazooAccount, $user)))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(
        Request $request,
        string $account,
        string $run,
        FindAccessibleKazooAccount $findAccount,
    ): SyncRunResource {
        /** @var User $user */
        $user = $request->user();
        $kazooAccount = $findAccount->handle($user, $account);
        $syncRun = $kazooAccount->syncRuns()->whereKey($run)->firstOrFail();

        return new SyncRunResource($syncRun);
    }
}
