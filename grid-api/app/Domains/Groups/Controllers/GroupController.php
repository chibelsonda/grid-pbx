<?php

namespace App\Domains\Groups\Controllers;

use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\Groups\Requests\ListGroupsRequest;
use App\Domains\Groups\Requests\SaveGroupRequest;
use App\Domains\Groups\Resources\GroupResource;
use App\Domains\Groups\Services\GroupMutationService;
use App\Domains\Groups\Services\GroupService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GroupController extends Controller
{
    public function index(ListGroupsRequest $request, string $account, SwitchAccountService $accounts, GroupService $groups): AnonymousResourceCollection
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchGroup::class, $switchAccount]);
        $validated = $request->validated();

        return GroupResource::collection($groups->paginate($switchAccount, $validated, (int) ($validated['per_page'] ?? 25)));
    }

    public function options(Request $request, string $account, SwitchAccountService $accounts, GroupService $groups): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchGroup::class, $switchAccount]);

        return ApiResponse::data($groups->options($switchAccount));
    }

    public function show(Request $request, string $account, string $group, SwitchAccountService $accounts, GroupService $groups): GroupResource
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $groups->find($switchAccount, $group);
        Gate::authorize('view', [$model, $switchAccount]);

        return new GroupResource($model);
    }

    public function store(SaveGroupRequest $request, string $account, SwitchAccountService $accounts, GroupMutationService $mutations): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchGroup::class, $switchAccount]);

        return (new GroupResource($mutations->create($switchAccount, $user, $request->validated(), $request->ip())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveGroupRequest $request, string $account, string $group, SwitchAccountService $accounts, GroupService $groups, GroupMutationService $mutations): GroupResource
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $groups->find($switchAccount, $group);
        Gate::authorize('update', [$model, $switchAccount]);

        return new GroupResource($mutations->update($switchAccount, $model, $user, $request->validated(), $request->ip()));
    }

    public function destroy(Request $request, string $account, string $group, SwitchAccountService $accounts, GroupService $groups, GroupMutationService $mutations): Response
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $groups->find($switchAccount, $group);
        Gate::authorize('delete', [$model, $switchAccount]);
        $mutations->delete($switchAccount, $model, $user, $request->ip());

        return ApiResponse::noContent();
    }
}
