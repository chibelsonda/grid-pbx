<?php

namespace App\Domains\TemporalRouting\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Requests\ListTemporalRoutingRequest;
use App\Domains\TemporalRouting\Requests\SaveTemporalRuleRequest;
use App\Domains\TemporalRouting\Resources\TemporalRuleResource;
use App\Domains\TemporalRouting\Services\TemporalRoutingService;
use App\Domains\TemporalRouting\Services\TemporalRuleMutationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TemporalRuleController extends Controller
{
    public function index(ListTemporalRoutingRequest $request, string $account, SwitchAccountService $accounts, TemporalRoutingService $service): AnonymousResourceCollection
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchTemporalRule::class, $switchAccount]);
        $data = $request->validated();

        return TemporalRuleResource::collection($service->rules($switchAccount, $data['search'] ?? null, (int) ($data['per_page'] ?? 25)));
    }

    public function show(Request $request, string $account, string $rule, SwitchAccountService $accounts, TemporalRoutingService $service): TemporalRuleResource
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->findRule($switchAccount, $rule);
        Gate::authorize('view', [$model, $switchAccount]);

        return new TemporalRuleResource($model);
    }

    public function store(SaveTemporalRuleRequest $request, string $account, SwitchAccountService $accounts, TemporalRuleMutationService $mutations): JsonResponse
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchTemporalRule::class, $switchAccount]);

        return (new TemporalRuleResource($mutations->create($switchAccount, $user, $request->validated(), $request->ip())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveTemporalRuleRequest $request, string $account, string $rule, SwitchAccountService $accounts, TemporalRoutingService $service, TemporalRuleMutationService $mutations): TemporalRuleResource
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->findRule($switchAccount, $rule);
        Gate::authorize('update', [$model, $switchAccount]);

        return new TemporalRuleResource($mutations->update($switchAccount, $model, $user, $request->validated(), $request->ip()));
    }

    public function destroy(Request $request, string $account, string $rule, SwitchAccountService $accounts, TemporalRoutingService $service, TemporalRuleMutationService $mutations): Response
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->findRule($switchAccount, $rule);
        Gate::authorize('delete', [$model, $switchAccount]);
        $mutations->delete($switchAccount, $model, $user, $request->ip());

        return response()->noContent();
    }
}
