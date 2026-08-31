<?php

namespace App\Domains\Payments\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Payments\Dto\PaymentReversalCommand;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Requests\CreateSandboxRefundRequest;
use App\Domains\Payments\Resources\PaymentAttemptResource;
use App\Domains\Payments\Services\PaymentAttemptLookupService;
use App\Domains\Payments\Services\PaymentReversalService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SandboxRefundController extends Controller
{
    public function __invoke(
        CreateSandboxRefundRequest $request,
        string $account,
        string $paymentAttempt,
        SwitchAccountService $accounts,
        PaymentAttemptLookupService $attempts,
        PaymentReversalService $reversals,
    ): JsonResponse {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('refund', [PaymentAttempt::class, $switchAccount]);
        $source = $attempts->findForAccount($switchAccount, $paymentAttempt);
        $outcome = $reversals->reverse($switchAccount, $user, $source, new PaymentReversalCommand(
            idempotencyKey: (string) $request->validated('idempotency_key'),
            operation: PaymentOperation::Refund,
            amountMinor: (int) $request->validated('amount_minor'),
            currency: (string) $request->validated('currency'),
        ));

        $response = ApiResponse::data(
            (new PaymentAttemptResource($outcome->attempt))->resolve($request),
            $outcome->replayed ? Response::HTTP_OK : Response::HTTP_CREATED,
            ['replayed' => $outcome->replayed],
        );

        if ($outcome->replayed) {
            $response->headers->set('Idempotent-Replay', 'true');
        }

        return $response;
    }
}
