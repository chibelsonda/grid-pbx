<?php

namespace App\Domains\GlobalSearch\Controllers;

use App\Domains\GlobalSearch\Enums\GlobalSearchType;
use App\Domains\GlobalSearch\Requests\GlobalSearchRequest;
use App\Domains\GlobalSearch\Services\GlobalSearchService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GlobalSearchController extends Controller
{
    public function __invoke(
        GlobalSearchRequest $request,
        string $account,
        SwitchAccountService $accounts,
        GlobalSearchService $search,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $authorizedTypes = collect($request->searchTypes())
            ->filter(static fn (GlobalSearchType $type): bool => Gate::forUser($user)->allows(
                'viewAny',
                [$type->modelClass(), $switchAccount],
            ))
            ->values()
            ->all();

        return ApiResponse::data($search->search(
            $switchAccount,
            $request->queryText(),
            $authorizedTypes,
        ));
    }
}
