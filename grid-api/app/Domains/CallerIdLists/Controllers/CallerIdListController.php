<?php

namespace App\Domains\CallerIdLists\Controllers;

use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\CallerIdLists\Requests\ListCallerIdListsRequest;
use App\Domains\CallerIdLists\Requests\SaveCallerIdListRequest;
use App\Domains\CallerIdLists\Resources\CallerIdListResource;
use App\Domains\CallerIdLists\Services\CallerIdListMutationService;
use App\Domains\CallerIdLists\Services\CallerIdListService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CallerIdListController extends Controller
{
    public function index(
        ListCallerIdListsRequest $request,
        string $account,
        SwitchAccountService $accounts,
        CallerIdListService $service,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchCallerIdList::class, $switchAccount]);
        $data = $request->validated();

        return CallerIdListResource::collection($service->list(
            $switchAccount,
            $data['search'] ?? null,
            (int) ($data['per_page'] ?? 25),
        ));
    }

    public function show(
        Request $request,
        string $account,
        string $callerIdList,
        SwitchAccountService $accounts,
        CallerIdListService $service,
    ): CallerIdListResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->find($switchAccount, $callerIdList);
        Gate::authorize('view', [$model, $switchAccount]);

        return new CallerIdListResource($model);
    }

    public function store(
        SaveCallerIdListRequest $request,
        string $account,
        SwitchAccountService $accounts,
        CallerIdListMutationService $mutations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchCallerIdList::class, $switchAccount]);

        return (new CallerIdListResource($mutations->create(
            $switchAccount,
            $user,
            $request->validated(),
            $request->ip(),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        SaveCallerIdListRequest $request,
        string $account,
        string $callerIdList,
        SwitchAccountService $accounts,
        CallerIdListService $service,
        CallerIdListMutationService $mutations,
    ): CallerIdListResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->find($switchAccount, $callerIdList);
        Gate::authorize('update', [$model, $switchAccount]);

        return new CallerIdListResource($mutations->update(
            $switchAccount,
            $model,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function destroy(
        Request $request,
        string $account,
        string $callerIdList,
        SwitchAccountService $accounts,
        CallerIdListService $service,
        CallerIdListMutationService $mutations,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->find($switchAccount, $callerIdList);
        Gate::authorize('delete', [$model, $switchAccount]);
        $mutations->delete($switchAccount, $model, $user, $request->ip());

        return ApiResponse::noContent();
    }
}
