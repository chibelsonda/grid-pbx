<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Resources\AccountHierarchyResource;
use App\Domains\Organizations\Resources\AccountResellerResource;
use App\Domains\Organizations\Services\AccountHierarchyService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountHierarchyController extends Controller
{
    public function hierarchy(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        AccountHierarchyService $hierarchy,
    ): AccountHierarchyResource {
        /** @var User $user */
        $user = $request->user();
        $model = $accounts->findMemberAccessible($user, $account);
        Gate::authorize('viewResellerAdministration', $model);

        return new AccountHierarchyResource($hierarchy->hierarchy($model));
    }

    public function reseller(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        AccountHierarchyService $hierarchy,
    ): AccountResellerResource {
        /** @var User $user */
        $user = $request->user();
        $model = $accounts->findMemberAccessible($user, $account);
        Gate::authorize('viewResellerAdministration', $model);

        return new AccountResellerResource($hierarchy->reseller($model));
    }
}
