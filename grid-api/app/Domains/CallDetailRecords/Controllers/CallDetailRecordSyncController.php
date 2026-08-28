<?php

namespace App\Domains\CallDetailRecords\Controllers;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\CallDetailRecords\Services\StartCallDetailRecordSyncService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Resources\SyncRunResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CallDetailRecordSyncController extends Controller
{
    public function store(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        StartCallDetailRecordSyncService $sync,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('sync', [SwitchCallDetailRecord::class, $switchAccount]);

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
        Gate::authorize('viewAny', [SwitchCallDetailRecord::class, $switchAccount]);
        $syncRun = $switchAccount->syncRuns()
            ->where('resource_type', 'call_detail_records')
            ->where('id', $run)
            ->firstOrFail();

        return new SyncRunResource($syncRun);
    }
}
