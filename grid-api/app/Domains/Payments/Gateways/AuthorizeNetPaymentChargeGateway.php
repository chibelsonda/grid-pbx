<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Payments\Contracts\PaymentChargeGateway;
use App\Domains\Payments\Dto\PaymentChargeCommand;
use App\Domains\Payments\Dto\PaymentMutationResult;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Exceptions\PaymentMutationUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

final class AuthorizeNetPaymentChargeGateway implements PaymentChargeGateway
{
    public function __construct(private readonly Factory $http) {}

    public function charge(PaymentChargeCommand $command, string $attemptId): PaymentMutationResult
    {
        $this->assertSandboxConfiguration();

        try {
            $response = $this->http
                ->asJson()
                ->acceptJson()
                ->connectTimeout((int) config('payments.authorize_net.connect_timeout', 5))
                ->timeout((int) config('payments.authorize_net.timeout', 10))
                ->post((string) config('payments.authorize_net.sandbox_endpoint'), [
                    'createTransactionRequest' => [
                        'merchantAuthentication' => [
                            'name' => (string) config('payments.authorize_net.api_login_id'),
                            'transactionKey' => (string) config('payments.authorize_net.transaction_key'),
                        ],
                        'refId' => substr($attemptId, 0, 20),
                        'transactionRequest' => [
                            'transactionType' => 'authCaptureTransaction',
                            'amount' => $this->decimalAmount($command->amountMinor),
                            'payment' => [
                                'opaqueData' => [
                                    'dataDescriptor' => $command->dataDescriptor,
                                    'dataValue' => $command->dataValue,
                                ],
                            ],
                            'transactionSettings' => [
                                'setting' => [
                                    'settingName' => 'duplicateWindow',
                                    'settingValue' => '120',
                                ],
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_connection_interrupted',
            );
        }

        return $this->mapResponse($response);
    }

    private function assertSandboxConfiguration(): void
    {
        $configured = filled(config('payments.authorize_net.api_login_id'))
            && filled(config('payments.authorize_net.transaction_key'));

        if (strtolower((string) config('payments.authorize_net.environment')) !== 'sandbox' || ! $configured) {
            throw new PaymentMutationUnavailableException;
        }
    }

    private function mapResponse(Response $response): PaymentMutationResult
    {
        if (! $response->successful()) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_http_error',
            );
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode(ltrim($response->body(), "\xEF\xBB\xBF"), true);

        if (! is_array($payload)) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_invalid_response',
            );
        }

        $transaction = data_get($payload, 'transactionResponse');
        $responseCode = (string) data_get($transaction, 'responseCode', '');
        $transactionId = trim((string) data_get($transaction, 'transId', ''));

        if ($responseCode === '1' && $transactionId !== '' && $transactionId !== '0') {
            return new PaymentMutationResult(PaymentAttemptStatus::Succeeded, $transactionId);
        }

        if (in_array($responseCode, ['2', '3'], true)) {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Failed,
                $transactionId !== '' && $transactionId !== '0' ? $transactionId : null,
                'payment_declined',
            );
        }

        if ($responseCode === '4') {
            return new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                $transactionId !== '' && $transactionId !== '0' ? $transactionId : null,
                'payment_held_for_review',
            );
        }

        $resultCode = strtolower((string) data_get($payload, 'messages.resultCode', ''));

        return new PaymentMutationResult(
            $resultCode === 'error' ? PaymentAttemptStatus::Failed : PaymentAttemptStatus::Indeterminate,
            safeErrorCode: $resultCode === 'error' ? 'provider_rejected_request' : 'provider_unknown_result',
        );
    }

    private function decimalAmount(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2, '.', '');
    }
}
