<?php

namespace App\Domains\Payments\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Resources\PaymentAttemptResource;
use App\Domains\Payments\Services\PaymentAttemptHistoryService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentAttemptController extends Controller
{
    public function index(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        PaymentAttemptHistoryService $history,
    ): JsonResponse {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [PaymentAttempt::class, $switchAccount]);
        $attempts = $history->recent($switchAccount, (int) $request->integer('limit', 25));

        return ApiResponse::data(
            PaymentAttemptResource::collection($attempts)->resolve($request),
        );
    }
}
