<?php

namespace App\Domains\Queues\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\Queues\Services\AgentAvailabilityService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentAvailabilityController extends Controller
{
    public function __invoke(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        AgentAvailabilityService $availability,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchQueue::class, $switchAccount]);

        return ApiResponse::data($availability->get($switchAccount));
    }
}
