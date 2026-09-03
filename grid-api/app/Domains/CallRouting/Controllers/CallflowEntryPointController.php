<?php

namespace App\Domains\CallRouting\Controllers;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\CallRouting\Requests\CheckCallflowExtensionAvailabilityRequest;
use App\Domains\CallRouting\Requests\ListCallflowExtensionsRequest;
use App\Domains\CallRouting\Services\CallflowEntryPointDiscoveryService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CallflowEntryPointController extends Controller
{
    public function index(
        ListCallflowExtensionsRequest $request,
        string $account,
        SwitchAccountService $accounts,
        CallflowEntryPointDiscoveryService $discovery,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchCallflow::class, $switchAccount]);
        $validated = $request->validated();
        $currentCallflow = $this->currentCallflow($switchAccount, $validated['callflow_id'] ?? null);

        return ApiResponse::data([
            'entries' => $discovery->directory(
                $switchAccount,
                $currentCallflow,
                $validated['search'] ?? null,
                (int) ($validated['limit'] ?? 50),
            ),
            'suggested_extension' => $discovery->availability(
                $switchAccount,
                '999',
                $currentCallflow,
            )['suggested_extension'],
        ]);
    }

    public function availability(
        CheckCallflowExtensionAvailabilityRequest $request,
        string $account,
        SwitchAccountService $accounts,
        CallflowEntryPointDiscoveryService $discovery,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchCallflow::class, $switchAccount]);
        $validated = $request->validated();

        return ApiResponse::data($discovery->availability(
            $switchAccount,
            $validated['number'],
            $this->currentCallflow($switchAccount, $validated['callflow_id'] ?? null),
        ));
    }

    private function currentCallflow(SwitchAccount $account, ?string $id): ?SwitchCallflow
    {
        if ($id === null) {
            return null;
        }

        return $account->callflows()->where('id', $id)->firstOrFail();
    }
}
