<?php

namespace App\Domains\Menus\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\Menus\Requests\ListMenusRequest;
use App\Domains\Menus\Requests\SaveMenuRequest;
use App\Domains\Menus\Resources\MenuResource;
use App\Domains\Menus\Services\MenuMutationService;
use App\Domains\Menus\Services\MenuService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MenuController extends Controller
{
    public function index(ListMenusRequest $request, string $account, SwitchAccountService $accounts, MenuService $menus): AnonymousResourceCollection
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchMenu::class, $switchAccount]);
        $validated = $request->validated();

        return MenuResource::collection($menus->paginate($switchAccount, $validated, (int) ($validated['per_page'] ?? 25)));
    }

    public function options(Request $request, string $account, SwitchAccountService $accounts, MenuService $menus): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchMenu::class, $switchAccount]);

        return response()->json(['data' => $menus->options($switchAccount)]);
    }

    public function show(Request $request, string $account, string $menu, SwitchAccountService $accounts, MenuService $menus): MenuResource
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $menus->find($switchAccount, $menu);
        Gate::authorize('view', [$model, $switchAccount]);

        return new MenuResource($model);
    }

    public function store(SaveMenuRequest $request, string $account, SwitchAccountService $accounts, MenuMutationService $mutations): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchMenu::class, $switchAccount]);

        return (new MenuResource($mutations->create($switchAccount, $user, $request->validated(), $request->ip())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveMenuRequest $request, string $account, string $menu, SwitchAccountService $accounts, MenuService $menus, MenuMutationService $mutations): MenuResource
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $menus->find($switchAccount, $menu);
        Gate::authorize('update', [$model, $switchAccount]);

        return new MenuResource($mutations->update($switchAccount, $model, $user, $request->validated(), $request->ip()));
    }

    public function destroy(Request $request, string $account, string $menu, SwitchAccountService $accounts, MenuService $menus, MenuMutationService $mutations): Response
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $menus->find($switchAccount, $menu);
        Gate::authorize('delete', [$model, $switchAccount]);
        $mutations->delete($switchAccount, $model, $user, $request->ip());

        return response()->noContent();
    }
}
