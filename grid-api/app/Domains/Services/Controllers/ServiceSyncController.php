<?php

namespace App\Domains\Services\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Services\Models\SwitchServiceSummary;
use App\Domains\Services\Services\StartServiceSyncService;
use App\Domains\SwitchSynchronization\Resources\SyncRunResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ServiceSyncController extends Controller
{
    public function store(Request $request, string $account, SwitchAccountService $accounts, StartServiceSyncService $sync): JsonResponse
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('sync', [SwitchServiceSummary::class, $switchAccount]);

        return (new SyncRunResource($sync->handle($switchAccount, $user)))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(Request $request, string $account, string $run, SwitchAccountService $accounts): SyncRunResource
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchServiceSummary::class, $switchAccount]);

        return new SyncRunResource($switchAccount->syncRuns()->where('resource_type', 'services')->where('id', $run)->firstOrFail());
    }
}
