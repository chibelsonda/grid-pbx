<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Requests\UpdateAccountSettingsRequest;
use App\Domains\Organizations\Resources\AccountDetailResource;
use App\Domains\Organizations\Resources\AccountResource;
use App\Domains\Organizations\Services\AccountSettingsService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function __invoke(Request $request, SwitchAccountService $accounts): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return AccountResource::collection($accounts->accessibleTo($user));
    }

    public function show(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
    ): AccountDetailResource {
        /** @var User $user */
        $user = $request->user();

        return new AccountDetailResource($accounts->findDetailedAccessible($user, $account));
    }

    public function update(
        UpdateAccountSettingsRequest $request,
        string $account,
        SwitchAccountService $accounts,
        AccountSettingsService $settings,
    ): AccountDetailResource {
        /** @var User $user */
        $user = $request->user();
        $model = $accounts->findMemberAccessible($user, $account);
        Gate::authorize('update', $model);
        $settings->update($model, $user, $request->validated(), $request->ip());

        return new AccountDetailResource($accounts->findDetailedAccessible($user, $account));
    }

    public function refresh(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        AccountSettingsService $settings,
    ): AccountDetailResource {
        /** @var User $user */
        $user = $request->user();
        $model = $accounts->findMemberAccessible($user, $account);
        Gate::authorize('refresh', $model);
        $settings->refresh($model, $user, $request->ip());

        return new AccountDetailResource($accounts->findDetailedAccessible($user, $account));
    }
}
