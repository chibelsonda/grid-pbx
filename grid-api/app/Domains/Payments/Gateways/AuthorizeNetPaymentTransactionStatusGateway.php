<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Payments\Contracts\PaymentTransactionStatusGateway;
use App\Domains\Payments\Dto\PaymentTransactionStatusResult;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

final class AuthorizeNetPaymentTransactionStatusGateway implements PaymentTransactionStatusGateway
{
    public function __construct(private readonly Factory $http) {}

    public function status(
        string $providerReference,
        PaymentOperation $operation,
    ): PaymentTransactionStatusResult {
        try {
            $response = $this->http
                ->asJson()
                ->acceptJson()
                ->connectTimeout((int) config('payments.authorize_net.connect_timeout', 5))
                ->timeout((int) config('payments.authorize_net.timeout', 10))
                ->post((string) config('payments.authorize_net.sandbox_endpoint'), [
                    'getTransactionDetailsRequest' => [
                        'merchantAuthentication' => [
                            'name' => (string) config('payments.authorize_net.api_login_id'),
                            'transactionKey' => (string) config('payments.authorize_net.transaction_key'),
                        ],
                        'transId' => $providerReference,
                    ],
                ]);
        } catch (ConnectionException) {
            return $this->retryable('provider_connection_interrupted');
        }

        if (! $response->successful()) {
            return $this->retryable('provider_http_error');
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode(ltrim($response->body(), "\xEF\xBB\xBF"), true);

        if (! is_array($payload)) {
            return $this->retryable('provider_invalid_response');
        }

        if (strtolower((string) data_get($payload, 'messages.resultCode')) !== 'ok') {
            return $this->retryable('transaction_details_unavailable');
        }

        $providerStatus = (string) data_get($payload, 'transaction.transactionStatus', '');
        $normalizedStatus = $this->normalizeProviderStatus($providerStatus);

        if ($this->operationSucceeded($operation, $providerStatus)) {
            return new PaymentTransactionStatusResult(
                PaymentAttemptStatus::Succeeded,
                $normalizedStatus,
            );
        }

        if (in_array($providerStatus, ['declined', 'expired', 'generalError', 'communicationError'], true)) {
            return new PaymentTransactionStatusResult(
                PaymentAttemptStatus::Failed,
                $normalizedStatus,
                'provider_transaction_failed',
            );
        }

        return new PaymentTransactionStatusResult(
            PaymentAttemptStatus::Indeterminate,
            $normalizedStatus,
            'provider_state_not_final',
            true,
        );
    }

    private function operationSucceeded(PaymentOperation $operation, string $providerStatus): bool
    {
        return match ($operation) {
            PaymentOperation::Charge => in_array($providerStatus, [
                'authorizedPendingCapture',
                'capturedPendingSettlement',
                'settledSuccessfully',
                'voided',
                'refundPendingSettlement',
                'refundSettledSuccessfully',
            ], true),
            PaymentOperation::Void => $providerStatus === 'voided',
            PaymentOperation::Refund => in_array($providerStatus, [
                'refundPendingSettlement',
                'refundSettledSuccessfully',
            ], true),
            PaymentOperation::AttachPaymentMethod => false,
        };
    }

    private function normalizeProviderStatus(string $status): string
    {
        return match ($status) {
            'authorizedPendingCapture' => 'authorized_pending_capture',
            'capturedPendingSettlement' => 'captured_pending_settlement',
            'settledSuccessfully' => 'settled',
            'voided' => 'voided',
            'refundPendingSettlement' => 'refund_pending_settlement',
            'refundSettledSuccessfully' => 'refund_settled',
            'declined' => 'declined',
            'expired' => 'expired',
            'generalError' => 'provider_error',
            'communicationError' => 'communication_error',
            default => 'unknown',
        };
    }

    private function retryable(string $safeErrorCode): PaymentTransactionStatusResult
    {
        return new PaymentTransactionStatusResult(
            PaymentAttemptStatus::Indeterminate,
            'unavailable',
            $safeErrorCode,
            true,
        );
    }
}
