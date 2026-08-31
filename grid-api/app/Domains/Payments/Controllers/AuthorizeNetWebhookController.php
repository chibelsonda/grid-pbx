<?php

namespace App\Domains\Payments\Controllers;

use App\Domains\Payments\Services\AuthorizeNetWebhookSignatureVerifier;
use App\Domains\Payments\Services\PaymentWebhookIntakeService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeNetWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        AuthorizeNetWebhookSignatureVerifier $signatures,
        PaymentWebhookIntakeService $intake,
    ): JsonResponse {
        if (! $this->available($signatures)) {
            return ApiResponse::error(
                'Payment webhook processing is unavailable.',
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $rawBody = $request->getContent();

        if (strlen($rawBody) > (int) config('payments.authorize_net.webhook_max_body_bytes', 65536)) {
            return ApiResponse::error('Webhook payload rejected.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if (! $signatures->verify($rawBody, $request->header('X-ANET-Signature'))) {
            return ApiResponse::error('Webhook signature rejected.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $receipt = $intake->receive($rawBody);
        } catch (ValidationException) {
            return ApiResponse::error('Webhook payload rejected.', Response::HTTP_BAD_REQUEST);
        }

        return ApiResponse::data([
            'accepted' => true,
            'replayed' => $receipt->replayed,
        ]);
    }

    private function available(AuthorizeNetWebhookSignatureVerifier $signatures): bool
    {
        return (bool) config('payments.authorize_net.webhook_enabled', false)
            && config('payments.provider') === 'authorize_net'
            && strtolower((string) config('payments.authorize_net.environment')) === 'sandbox'
            && filled(config('payments.authorize_net.api_login_id'))
            && filled(config('payments.authorize_net.transaction_key'))
            && $signatures->configured();
    }
}
