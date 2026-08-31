<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Payments\Contracts\PaymentReversalGateway;
use App\Domains\Payments\Dto\PaymentMutationResult;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Exceptions\PaymentMutationUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

final class AuthorizeNetPaymentReversalGateway implements PaymentReversalGateway
{
    public function __construct(private readonly Factory $http) {}

    public function void(string $providerReference, string $attemptId): PaymentMutationResult
    {
        $this->assertSandboxConfiguration();

        try {
            $details = $this->transactionDetails($providerReference);

            if ($details instanceof PaymentMutationResult) {
                return $details;
            }

            $status = (string) data_get($details, 'transactionStatus', '');

            if ($status === 'settledSuccessfully') {
                return new PaymentMutationResult(
                    PaymentAttemptStatus::Failed,
                    safeErrorCode: 'transaction_already_settled',
                );
            }

            if (in_array($status, ['voided', 'refundSettledSuccessfully', 'refundPendingSettlement'], true)) {
                return new PaymentMutationResult(
                    PaymentAttemptStatus::Failed,
                    safeErrorCode: 'transaction_not_voidable',
                );
            }

            $response = $this->post([
                'createTransactionRequest' => [
                    'merchantAuthentication' => $this->authentication(),
                    'refId' => substr($attemptId, 0, 20),
                    'transactionRequest' => [
                        'transactionType' => 'voidTransaction',
                        'refTransId' => $providerReference,
                    ],
                ],
            ]);
        } catch (ConnectionException) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_connection_interrupted',
            );
        }

        return $this->mapTransactionResponse($response, 'void');
    }

    public function refund(
        string $providerReference,
        int $amountMinor,
        string $currency,
        string $attemptId,
    ): PaymentMutationResult {
        $this->assertSandboxConfiguration();

        if ($currency !== 'USD') {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Failed,
                safeErrorCode: 'unsupported_currency',
            );
        }

        try {
            $details = $this->transactionDetails($providerReference);

            if ($details instanceof PaymentMutationResult) {
                return $details;
            }

            if ((string) data_get($details, 'transactionStatus', '') !== 'settledSuccessfully') {
                return new PaymentMutationResult(
                    PaymentAttemptStatus::Failed,
                    safeErrorCode: 'transaction_not_settled',
                );
            }

            $maskedCardNumber = trim((string) data_get($details, 'payment.creditCard.cardNumber', ''));

            if (! preg_match('/^(?:X{4}|\*{4})\d{4}$/i', $maskedCardNumber)) {
                return new PaymentMutationResult(
                    PaymentAttemptStatus::Failed,
                    safeErrorCode: 'unsupported_refund_payment_method',
                );
            }

            $response = $this->post([
                'createTransactionRequest' => [
                    'merchantAuthentication' => $this->authentication(),
                    'refId' => substr($attemptId, 0, 20),
                    'transactionRequest' => [
                        'transactionType' => 'refundTransaction',
                        'amount' => $this->decimalAmount($amountMinor),
                        'payment' => [
                            'creditCard' => [
                                'cardNumber' => $maskedCardNumber,
                                'expirationDate' => 'XXXX',
                            ],
                        ],
                        'refTransId' => $providerReference,
                    ],
                ],
            ]);
        } catch (ConnectionException) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_connection_interrupted',
            );
        }

        return $this->mapTransactionResponse($response, 'refund');
    }

    /** @return array<string, mixed>|PaymentMutationResult */
    private function transactionDetails(string $providerReference): array|PaymentMutationResult
    {
        $response = $this->post([
            'getTransactionDetailsRequest' => [
                'merchantAuthentication' => $this->authentication(),
                'transId' => $providerReference,
            ],
        ]);

        if (! $response->successful()) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_http_error',
            );
        }

        $payload = $this->decode($response);

        if ($payload === null) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_invalid_response',
            );
        }

        if (strtolower((string) data_get($payload, 'messages.resultCode', '')) !== 'ok') {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Failed,
                safeErrorCode: 'transaction_details_unavailable',
            );
        }

        $transaction = data_get($payload, 'transaction');

        if (! is_array($transaction)) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_invalid_response',
            );
        }

        return $transaction;
    }

    /** @param array<string, mixed> $payload */
    private function post(array $payload): Response
    {
        return $this->http
            ->asJson()
            ->acceptJson()
            ->connectTimeout((int) config('payments.authorize_net.connect_timeout', 5))
            ->timeout((int) config('payments.authorize_net.timeout', 10))
            ->post((string) config('payments.authorize_net.sandbox_endpoint'), $payload);
    }

    private function mapTransactionResponse(Response $response, string $operation): PaymentMutationResult
    {
        if (! $response->successful()) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_http_error',
            );
        }

        $payload = $this->decode($response);

        if ($payload === null) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_invalid_response',
            );
        }

        $responseCode = (string) data_get($payload, 'transactionResponse.responseCode', '');
        $transactionId = trim((string) data_get($payload, 'transactionResponse.transId', ''));

        if ($responseCode === '1' && $transactionId !== '' && $transactionId !== '0') {
            return new PaymentMutationResult(PaymentAttemptStatus::Succeeded, $transactionId);
        }

        if (in_array($responseCode, ['2', '3'], true)) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Failed,
                $transactionId !== '' && $transactionId !== '0' ? $transactionId : null,
                "{$operation}_rejected",
            );
        }

        return new PaymentMutationResult(
            PaymentAttemptStatus::Indeterminate,
            safeErrorCode: 'provider_unknown_result',
        );
    }

    /** @return array<string, mixed>|null */
    private function decode(Response $response): ?array
    {
        $payload = json_decode(ltrim($response->body(), "\xEF\xBB\xBF"), true);

        return is_array($payload) ? $payload : null;
    }

    /** @return array{name: string, transactionKey: string} */
    private function authentication(): array
    {
        return [
            'name' => (string) config('payments.authorize_net.api_login_id'),
            'transactionKey' => (string) config('payments.authorize_net.transaction_key'),
        ];
    }

    private function assertSandboxConfiguration(): void
    {
        $configured = filled(config('payments.authorize_net.api_login_id'))
            && filled(config('payments.authorize_net.transaction_key'));

        if (strtolower((string) config('payments.authorize_net.environment')) !== 'sandbox' || ! $configured) {
            throw new PaymentMutationUnavailableException;
        }
    }

    private function decimalAmount(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2, '.', '');
    }
}
