<?php

namespace App\Domains\Extensions\Controllers;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Extensions\Requests\ListExtensionsRequest;
use App\Domains\Extensions\Requests\RecoverExtensionOperationRequest;
use App\Domains\Extensions\Services\ExtensionRecoveryQueryService;
use App\Domains\Extensions\Services\ExtensionRecoveryService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ExtensionRecoveryController extends Controller
{
    public function index(
        ListExtensionsRequest $request,
        string $account,
        SwitchAccountService $accounts,
        ExtensionRecoveryQueryService $recovery,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchExtension::class, $switchAccount]);

        return ApiResponse::data(
            $recovery->pending($switchAccount)
                ->map(fn ($operation): array => $recovery->summary($operation))
                ->all(),
        );
    }

    public function recover(
        RecoverExtensionOperationRequest $request,
        string $account,
        string $operation,
        SwitchAccountService $accounts,
        ExtensionRecoveryQueryService $query,
        ExtensionRecoveryService $recovery,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchExtension::class, $switchAccount]);
        $lifecycleOperation = $query->find($switchAccount, $operation);
        $recovered = $recovery->recover(
            $switchAccount,
            $lifecycleOperation,
            $user,
            $request->validated('confirmation'),
            $request->ip(),
        );

        return ApiResponse::data($query->summary($recovered));
    }
}
