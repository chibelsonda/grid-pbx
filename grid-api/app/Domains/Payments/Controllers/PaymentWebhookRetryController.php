<?php

namespace App\Domains\Payments\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Resources\PaymentWebhookDeliveryResource;
use App\Domains\Payments\Services\PaymentWebhookRecoveryService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookRetryController extends Controller
{
    public function __invoke(
        Request $request,
        string $account,
        string $paymentWebhookDelivery,
        SwitchAccountService $accounts,
        PaymentWebhookRecoveryService $recovery,
    ): JsonResponse {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('retryWebhookReconciliation', [PaymentAttempt::class, $switchAccount]);
        $delivery = $recovery->retry(
            $switchAccount,
            $paymentWebhookDelivery,
            $user,
            $request->ip(),
        );

        return ApiResponse::data(
            (new PaymentWebhookDeliveryResource($delivery))->resolve($request),
            Response::HTTP_ACCEPTED,
        );
    }
}
