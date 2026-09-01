<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Requests\StoreOrganizationLogoRequest;
use App\Domains\Organizations\Resources\OrganizationBrandingResource;
use App\Domains\Organizations\Services\OrganizationLogoService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class OrganizationLogoController extends Controller
{
    public function show(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        OrganizationLogoService $logos,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        return $logos->response($accounts->findMemberAccessible($user, $account));
    }

    public function store(
        StoreOrganizationLogoRequest $request,
        string $account,
        SwitchAccountService $accounts,
        OrganizationLogoService $logos,
    ): OrganizationBrandingResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findMemberAccessible($user, $account);
        Gate::authorize('update', $switchAccount);

        return new OrganizationBrandingResource($logos->store(
            $switchAccount,
            $user,
            $request->file('logo'),
            $request->ip(),
        ));
    }

    public function destroy(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        OrganizationLogoService $logos,
    ): OrganizationBrandingResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findMemberAccessible($user, $account);
        Gate::authorize('update', $switchAccount);

        return new OrganizationBrandingResource($logos->destroy(
            $switchAccount,
            $user,
            $request->ip(),
        ));
    }
}
