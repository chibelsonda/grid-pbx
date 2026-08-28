<?php

namespace App\Domains\TemporalRouting\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Resources\SyncRunResource;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Services\StartTemporalRoutingSyncService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TemporalRoutingSyncController extends Controller
{
    public function store(Request $request, string $account, SwitchAccountService $accounts, StartTemporalRoutingSyncService $sync): JsonResponse
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('sync', [SwitchTemporalRule::class, $switchAccount]);

        return (new SyncRunResource($sync->handle($switchAccount, $user)))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(Request $request, string $account, string $run, SwitchAccountService $accounts): SyncRunResource
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchTemporalRule::class, $switchAccount]);

        return new SyncRunResource($switchAccount->syncRuns()->where('resource_type', 'temporal_routing')->where('id', $run)->firstOrFail());
    }
}
