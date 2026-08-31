<?php

namespace App\Domains\Payments\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Payments\Dto\PaymentProfileCommand;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Requests\CreateSandboxPaymentProfileRequest;
use App\Domains\Payments\Resources\PaymentAttemptResource;
use App\Domains\Payments\Resources\PaymentCustomerProfileResource;
use App\Domains\Payments\Services\PaymentAttemptLookupService;
use App\Domains\Payments\Services\PaymentProfileService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SandboxPaymentProfileController extends Controller
{
    public function __invoke(
        CreateSandboxPaymentProfileRequest $request,
        string $account,
        string $paymentAttempt,
        SwitchAccountService $accounts,
        PaymentAttemptLookupService $attempts,
        PaymentProfileService $profiles,
    ): JsonResponse {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('attachPaymentMethod', [PaymentAttempt::class, $switchAccount]);
        $source = $attempts->findForAccount($switchAccount, $paymentAttempt);
        $outcome = $profiles->createFromCharge($switchAccount, $user, $source, new PaymentProfileCommand(
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));

        $response = ApiResponse::data([
            'attempt' => (new PaymentAttemptResource($outcome->attempt))->resolve($request),
            'profile' => $outcome->profile === null
                ? null
                : (new PaymentCustomerProfileResource($outcome->profile))->resolve($request),
        ], $outcome->replayed ? Response::HTTP_OK : Response::HTTP_CREATED, [
            'replayed' => $outcome->replayed,
        ]);

        if ($outcome->replayed) {
            $response->headers->set('Idempotent-Replay', 'true');
        }

        return $response;
    }
}
