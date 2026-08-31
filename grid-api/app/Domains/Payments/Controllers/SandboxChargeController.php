<?php

namespace App\Domains\Payments\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Payments\Dto\PaymentChargeCommand;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Requests\CreateSandboxChargeRequest;
use App\Domains\Payments\Resources\PaymentAttemptResource;
use App\Domains\Payments\Services\PaymentChargeService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SandboxChargeController extends Controller
{
    public function __invoke(
        CreateSandboxChargeRequest $request,
        string $account,
        SwitchAccountService $accounts,
        PaymentChargeService $charges,
    ): JsonResponse {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('charge', [PaymentAttempt::class, $switchAccount]);

        /** @var array{dataDescriptor: string, dataValue: string} $opaqueData */
        $opaqueData = $request->validated('opaque_data');
        $outcome = $charges->charge($switchAccount, $user, new PaymentChargeCommand(
            idempotencyKey: (string) $request->validated('idempotency_key'),
            amountMinor: (int) $request->validated('amount_minor'),
            currency: (string) $request->validated('currency'),
            dataDescriptor: $opaqueData['dataDescriptor'],
            dataValue: $opaqueData['dataValue'],
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
