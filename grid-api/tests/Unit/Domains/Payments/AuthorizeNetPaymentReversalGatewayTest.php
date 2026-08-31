<?php

namespace Tests\Unit\Domains\Payments;

use App\Domains\Payments\Contracts\PaymentReversalGateway;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthorizeNetPaymentReversalGatewayTest extends TestCase
{
    public function test_void_checks_transaction_state_and_sends_only_the_server_resolved_reference(): void
    {
        $this->configureSandbox();
        Http::preventStrayRequests();
        Http::fakeSequence('https://apitest.authorize.net/xml/v1/request.api')
            ->push([
                'messages' => ['resultCode' => 'Ok'],
                'transaction' => ['transactionStatus' => 'capturedPendingSettlement'],
            ])
            ->push([
                'messages' => ['resultCode' => 'Ok'],
                'transactionResponse' => [
                    'responseCode' => '1',
                    'transId' => 'provider-void-123',
                ],
            ]);

        $result = app(PaymentReversalGateway::class)->void(
            'private-source-transaction',
            'public-void-attempt',
        );

        $this->assertSame(PaymentAttemptStatus::Succeeded, $result->status);
        $this->assertSame('provider-void-123', $result->providerReference);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return data_get($payload, 'createTransactionRequest.transactionRequest.transactionType') === 'voidTransaction'
                && data_get($payload, 'createTransactionRequest.transactionRequest.refTransId') === 'private-source-transaction'
                && data_get($payload, 'createTransactionRequest.transactionRequest.payment') === null;
        });
    }

    public function test_refund_fetches_masked_payment_data_and_never_uses_a_full_card_number(): void
    {
        $this->configureSandbox();
        Http::preventStrayRequests();
        Http::fakeSequence('https://apitest.authorize.net/xml/v1/request.api')
            ->push([
                'messages' => ['resultCode' => 'Ok'],
                'transaction' => [
                    'transactionStatus' => 'settledSuccessfully',
                    'payment' => [
                        'creditCard' => ['cardNumber' => 'XXXX1111'],
                    ],
                ],
            ])
            ->push([
                'messages' => ['resultCode' => 'Ok'],
                'transactionResponse' => [
                    'responseCode' => '1',
                    'transId' => 'provider-refund-123',
                ],
            ]);

        $result = app(PaymentReversalGateway::class)->refund(
            'private-source-transaction',
            75,
            'USD',
            'public-refund-attempt',
        );

        $this->assertSame(PaymentAttemptStatus::Succeeded, $result->status);
        $this->assertSame('provider-refund-123', $result->providerReference);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $serialized = json_encode($payload, JSON_THROW_ON_ERROR);

            return data_get($payload, 'createTransactionRequest.transactionRequest.transactionType') === 'refundTransaction'
                && data_get($payload, 'createTransactionRequest.transactionRequest.amount') === '0.75'
                && data_get($payload, 'createTransactionRequest.transactionRequest.refTransId') === 'private-source-transaction'
                && data_get($payload, 'createTransactionRequest.transactionRequest.payment.creditCard.cardNumber') === 'XXXX1111'
                && data_get($payload, 'createTransactionRequest.transactionRequest.payment.creditCard.expirationDate') === 'XXXX'
                && ! str_contains($serialized, '4111111111111111');
        });
    }

    public function test_refund_fails_safely_without_submitting_when_charge_is_not_settled(): void
    {
        $this->configureSandbox();
        Http::preventStrayRequests();
        Http::fake([
            'https://apitest.authorize.net/xml/v1/request.api' => Http::response([
                'messages' => ['resultCode' => 'Ok'],
                'transaction' => ['transactionStatus' => 'capturedPendingSettlement'],
            ]),
        ]);

        $result = app(PaymentReversalGateway::class)->refund(
            'private-source-transaction',
            100,
            'USD',
            'public-refund-attempt',
        );

        $this->assertSame(PaymentAttemptStatus::Failed, $result->status);
        $this->assertSame('transaction_not_settled', $result->safeErrorCode);
        Http::assertSentCount(1);
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
}
