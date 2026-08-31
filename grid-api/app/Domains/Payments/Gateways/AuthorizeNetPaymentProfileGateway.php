<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Payments\Contracts\PaymentProfileGateway;
use App\Domains\Payments\Dto\PaymentProfileResult;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Exceptions\PaymentMutationUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

final class AuthorizeNetPaymentProfileGateway implements PaymentProfileGateway
{
    public function __construct(private readonly Factory $http) {}

    public function createFromTransaction(
        string $providerReference,
        string $merchantCustomerId,
        string $description,
        ?string $email,
    ): PaymentProfileResult {
        $this->assertSandboxConfiguration();

        $customer = array_filter([
            'merchantCustomerId' => $merchantCustomerId,
            'description' => $description,
            'email' => $email,
        ], static fn (mixed $value): bool => filled($value));

        try {
            $response = $this->http
                ->asJson()
                ->acceptJson()
                ->connectTimeout((int) config('payments.authorize_net.connect_timeout', 5))
                ->timeout((int) config('payments.authorize_net.timeout', 10))
                ->post((string) config('payments.authorize_net.sandbox_endpoint'), [
                    'createCustomerProfileFromTransactionRequest' => [
                        'merchantAuthentication' => [
                            'name' => (string) config('payments.authorize_net.api_login_id'),
                            'transactionKey' => (string) config('payments.authorize_net.transaction_key'),
                        ],
                        'transId' => $providerReference,
                        'customer' => $customer,
                    ],
                ]);
        } catch (ConnectionException) {
            return new PaymentProfileResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_connection_interrupted',
            );
        }

        if (! $response->successful()) {
            return new PaymentProfileResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_http_error',
            );
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode(ltrim($response->body(), "\xEF\xBB\xBF"), true);

        if (! is_array($payload)) {
            return new PaymentProfileResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_invalid_response',
            );
        }

        $customerProfileId = trim((string) data_get($payload, 'customerProfileId', ''));
        $paymentProfileIds = data_get($payload, 'customerPaymentProfileIdList.numericString', []);

        if (is_string($paymentProfileIds)) {
            $paymentProfileIds = [$paymentProfileIds];
        }

        $paymentProfileId = is_array($paymentProfileIds)
            ? trim((string) ($paymentProfileIds[0] ?? ''))
            : '';

        if (
            strtolower((string) data_get($payload, 'messages.resultCode', '')) === 'ok'
            && $customerProfileId !== ''
            && $paymentProfileId !== ''
        ) {
            return new PaymentProfileResult(
                PaymentAttemptStatus::Succeeded,
                $customerProfileId,
                $paymentProfileId,
            );
        }

        return new PaymentProfileResult(
            PaymentAttemptStatus::Failed,
            safeErrorCode: 'profile_creation_rejected',
        );
    }

    private function assertSandboxConfiguration(): void
    {
        $configured = filled(config('payments.authorize_net.api_login_id'))
            && filled(config('payments.authorize_net.transaction_key'));

        if (strtolower((string) config('payments.authorize_net.environment')) !== 'sandbox' || ! $configured) {
            throw new PaymentMutationUnavailableException;
        }
    }
}
