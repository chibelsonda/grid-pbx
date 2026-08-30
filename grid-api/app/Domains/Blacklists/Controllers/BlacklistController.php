<?php

namespace App\Domains\Blacklists\Controllers;

use App\Domains\Blacklists\Models\SwitchBlacklist;
use App\Domains\Blacklists\Requests\ListBlacklistsRequest;
use App\Domains\Blacklists\Requests\SaveBlacklistRequest;
use App\Domains\Blacklists\Resources\BlacklistResource;
use App\Domains\Blacklists\Services\BlacklistMutationService;
use App\Domains\Blacklists\Services\BlacklistService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BlacklistController extends Controller
{
    public function index(ListBlacklistsRequest $request, string $account, SwitchAccountService $accounts, BlacklistService $service): AnonymousResourceCollection
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchBlacklist::class, $switchAccount]);
        $data = $request->validated();

        return BlacklistResource::collection($service->list($switchAccount, $data['search'] ?? null, (int) ($data['per_page'] ?? 25)));
    }

    public function show(Request $request, string $account, string $blacklist, SwitchAccountService $accounts, BlacklistService $service): BlacklistResource
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->find($switchAccount, $blacklist);
        Gate::authorize('view', [$model, $switchAccount]);

        return new BlacklistResource($model);
    }

    public function store(SaveBlacklistRequest $request, string $account, SwitchAccountService $accounts, BlacklistMutationService $mutations): JsonResponse
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchBlacklist::class, $switchAccount]);

        return (new BlacklistResource($mutations->create($switchAccount, $user, $request->validated(), $request->ip())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveBlacklistRequest $request, string $account, string $blacklist, SwitchAccountService $accounts, BlacklistService $service, BlacklistMutationService $mutations): BlacklistResource
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->find($switchAccount, $blacklist);
        Gate::authorize('update', [$model, $switchAccount]);

        return new BlacklistResource($mutations->update($switchAccount, $model, $user, $request->validated(), $request->ip()));
    }

    public function destroy(Request $request, string $account, string $blacklist, SwitchAccountService $accounts, BlacklistService $service, BlacklistMutationService $mutations): Response
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->find($switchAccount, $blacklist);
        Gate::authorize('delete', [$model, $switchAccount]);
        $mutations->delete($switchAccount, $model, $user, $request->ip());

        return ApiResponse::noContent();
    }
}
