<?php

namespace App\Domains\TemporalRouting\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use App\Domains\TemporalRouting\Requests\ListTemporalRoutingRequest;
use App\Domains\TemporalRouting\Requests\SaveTemporalRuleSetRequest;
use App\Domains\TemporalRouting\Resources\TemporalRuleSetResource;
use App\Domains\TemporalRouting\Services\TemporalRoutingService;
use App\Domains\TemporalRouting\Services\TemporalRuleSetMutationService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TemporalRuleSetController extends Controller
{
    public function index(ListTemporalRoutingRequest $request, string $account, SwitchAccountService $accounts, TemporalRoutingService $service): AnonymousResourceCollection
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchTemporalRuleSet::class, $switchAccount]);
        $data = $request->validated();

        return TemporalRuleSetResource::collection($service->sets($switchAccount, $data['search'] ?? null, (int) ($data['per_page'] ?? 25)));
    }

    public function options(Request $request, string $account, SwitchAccountService $accounts, TemporalRoutingService $service): JsonResponse
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchTemporalRuleSet::class, $switchAccount]);

        return ApiResponse::data($service->options($switchAccount));
    }

    public function show(Request $request, string $account, string $set, SwitchAccountService $accounts, TemporalRoutingService $service): TemporalRuleSetResource
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->findSet($switchAccount, $set);
        Gate::authorize('view', [$model, $switchAccount]);

        return new TemporalRuleSetResource($model);
    }

    public function store(SaveTemporalRuleSetRequest $request, string $account, SwitchAccountService $accounts, TemporalRuleSetMutationService $mutations): JsonResponse
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchTemporalRuleSet::class, $switchAccount]);

        return (new TemporalRuleSetResource($mutations->create($switchAccount, $user, $request->validated(), $request->ip())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveTemporalRuleSetRequest $request, string $account, string $set, SwitchAccountService $accounts, TemporalRoutingService $service, TemporalRuleSetMutationService $mutations): TemporalRuleSetResource
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->findSet($switchAccount, $set);
        Gate::authorize('update', [$model, $switchAccount]);

        return new TemporalRuleSetResource($mutations->update($switchAccount, $model, $user, $request->validated(), $request->ip()));
    }

    public function destroy(Request $request, string $account, string $set, SwitchAccountService $accounts, TemporalRoutingService $service, TemporalRuleSetMutationService $mutations): Response
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->findSet($switchAccount, $set);
        Gate::authorize('delete', [$model, $switchAccount]);
        $mutations->delete($switchAccount, $model, $user, $request->ip());

        return ApiResponse::noContent();
    }
}
