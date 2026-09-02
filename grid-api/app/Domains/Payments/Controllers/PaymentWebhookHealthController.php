<?php

namespace App\Domains\Payments\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Requests\ListPaymentWebhookDeliveriesRequest;
use App\Domains\Payments\Resources\PaymentWebhookDeliveryResource;
use App\Domains\Payments\Services\PaymentWebhookHealthService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PaymentWebhookHealthController extends Controller
{
    public function __invoke(
        ListPaymentWebhookDeliveriesRequest $request,
        string $account,
        SwitchAccountService $accounts,
        PaymentWebhookHealthService $health,
    ): JsonResponse {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [PaymentAttempt::class, $switchAccount]);
        $result = $health->get($switchAccount, $request->integer('limit', 25));

        return ApiResponse::data([
            'summary' => $result['summary'],
            'recovery_available' => $result['recovery_available'],
            'deliveries' => PaymentWebhookDeliveryResource::collection($result['deliveries'])
                ->resolve($request),
        ]);
    }
}
