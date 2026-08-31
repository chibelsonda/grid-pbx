<?php

namespace Tests\Unit\Domains\Payments;

use App\Domains\Payments\Contracts\PaymentChargeGateway;
use App\Domains\Payments\Dto\PaymentChargeCommand;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthorizeNetPaymentChargeGatewayTest extends TestCase
{
    public function test_it_sends_only_the_opaque_payment_token_and_maps_success(): void
    {
        $this->configureSandbox();
        Http::preventStrayRequests();
        Http::fake([
            'https://apitest.authorize.net/xml/v1/request.api' => Http::response(
                "\xEF\xBB\xBF".json_encode([
                    'transactionResponse' => [
                        'responseCode' => '1',
                        'transId' => 'provider-transaction-123',
                    ],
                    'messages' => ['resultCode' => 'Ok'],
                ], JSON_THROW_ON_ERROR),
            ),
        ]);

        $result = app(PaymentChargeGateway::class)->charge($this->command(), 'public-attempt-uuid');

        $this->assertSame(PaymentAttemptStatus::Succeeded, $result->status);
        $this->assertSame('provider-transaction-123', $result->providerReference);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $serialized = json_encode($payload, JSON_THROW_ON_ERROR);

            return $request->url() === 'https://apitest.authorize.net/xml/v1/request.api'
                && data_get($payload, 'createTransactionRequest.transactionRequest.amount') === '1.00'
                && data_get($payload, 'createTransactionRequest.transactionRequest.payment.opaqueData.dataValue') === 'opaque-payment-token-value'
                && data_get($payload, 'createTransactionRequest.transactionRequest.payment.opaqueData.dataDescriptor') === 'COMMON.ACCEPT.INAPP.PAYMENT'
                && ! str_contains($serialized, 'cardNumber')
                && ! str_contains($serialized, 'cardCode');
        });
    }

    public function test_it_maps_declines_to_a_safe_code(): void
    {
        $this->configureSandbox();
        Http::preventStrayRequests();
        Http::fake([
            'https://apitest.authorize.net/xml/v1/request.api' => Http::response([
                'transactionResponse' => [
                    'responseCode' => '2',
                    'transId' => '0',
                    'errors' => [[
                        'errorCode' => 'private-provider-code',
                        'errorText' => 'Private provider decline detail.',
                    ]],
                ],
                'messages' => ['resultCode' => 'Error'],
            ]),
        ]);

        $result = app(PaymentChargeGateway::class)->charge($this->command(), 'public-attempt-uuid');

        $this->assertSame(PaymentAttemptStatus::Failed, $result->status);
        $this->assertSame('payment_declined', $result->safeErrorCode);
        $this->assertNull($result->providerReference);
    }

    private function configureSandbox(): void
    {
        config()->set([
            'payments.provider' => 'authorize_net',
            'payments.authorize_net.environment' => 'sandbox',
            'payments.authorize_net.api_login_id' => 'sandbox-login',
            'payments.authorize_net.transaction_key' => 'sandbox-transaction-key',
            'payments.authorize_net.sandbox_endpoint' => 'https://apitest.authorize.net/xml/v1/request.api',
        ]);
    }

    private function command(): PaymentChargeCommand
    {
        return new PaymentChargeCommand(
            idempotencyKey: 'idempotency-key-00001',
            amountMinor: 100,
            currency: 'USD',
            dataDescriptor: 'COMMON.ACCEPT.INAPP.PAYMENT',
            dataValue: 'opaque-payment-token-value',
        );
    }
}
