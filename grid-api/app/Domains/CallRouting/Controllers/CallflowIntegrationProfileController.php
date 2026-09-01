<?php

namespace App\Domains\CallRouting\Controllers;

use App\Domains\CallRouting\Requests\StoreCallflowIntegrationProfileRequest;
use App\Domains\CallRouting\Requests\UpdateCallflowIntegrationProfileRequest;
use App\Domains\CallRouting\Resources\CallflowIntegrationProfileResource;
use App\Domains\CallRouting\Services\CallflowIntegrationProfileService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CallflowIntegrationProfileController extends Controller
{
    public function index(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        CallflowIntegrationProfileService $profiles,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('update', $switchAccount);

        return CallflowIntegrationProfileResource::collection($profiles->list($switchAccount));
    }

    public function store(
        StoreCallflowIntegrationProfileRequest $request,
        string $account,
        SwitchAccountService $accounts,
        CallflowIntegrationProfileService $profiles,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('update', $switchAccount);

        return (new CallflowIntegrationProfileResource($profiles->create(
            $switchAccount,
            $user,
            $request->validated(),
            $request->ip(),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdateCallflowIntegrationProfileRequest $request,
        string $account,
        string $profile,
        SwitchAccountService $accounts,
        CallflowIntegrationProfileService $profiles,
    ): CallflowIntegrationProfileResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('update', $switchAccount);

        return new CallflowIntegrationProfileResource($profiles->update(
            $switchAccount,
            $profiles->find($switchAccount, $profile),
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function destroy(
        Request $request,
        string $account,
        string $profile,
        SwitchAccountService $accounts,
        CallflowIntegrationProfileService $profiles,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('update', $switchAccount);
        $profiles->delete(
            $switchAccount,
            $profiles->find($switchAccount, $profile),
            $user,
            $request->ip(),
        );

        return ApiResponse::noContent();
    }
}
