<?php

namespace App\Domains\Queues\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\Queues\Requests\UpdateAgentStatusRequest;
use App\Domains\Queues\Services\AgentService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AgentController extends Controller
{
    public function index(Request $request, string $account, SwitchAccountService $accounts, AgentService $agents): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchQueue::class, $switchAccount]);

        return ApiResponse::data($agents->all($switchAccount));
    }

    public function status(Request $request, string $account, string $agent, SwitchAccountService $accounts, AgentService $agents): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchQueue::class, $switchAccount]);

        return ApiResponse::data($agents->status($switchAccount, $agent));
    }

    public function updateStatus(UpdateAgentStatusRequest $request, string $account, string $agent, SwitchAccountService $accounts, AgentService $agents): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchQueue::class, $switchAccount]);
        $validated = $request->validated();
        $agents->updateStatus($switchAccount, $agent, $validated['status'], $validated['pause_timeout'] ?? null, $user, $request->ip());

        return ApiResponse::data(
            ['id' => $agent, 'requested_status' => $validated['status']],
            Response::HTTP_ACCEPTED,
        );
    }
}
