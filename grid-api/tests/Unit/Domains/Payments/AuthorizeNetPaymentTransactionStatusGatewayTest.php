<?php

namespace Tests\Unit\Domains\Payments;

use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Gateways\AuthorizeNetPaymentTransactionStatusGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthorizeNetPaymentTransactionStatusGatewayTest extends TestCase
{
    public function test_it_confirms_a_settled_charge_without_exposing_the_reference(): void
    {
        $this->configureProvider();
        Http::fake([
            'https://apitest.authorize.net/*' => Http::response([
                'messages' => ['resultCode' => 'Ok'],
                'transaction' => ['transactionStatus' => 'settledSuccessfully'],
            ]),
        ]);

        $result = app(AuthorizeNetPaymentTransactionStatusGateway::class)
            ->status('private-provider-reference', PaymentOperation::Charge);

        $this->assertSame(PaymentAttemptStatus::Succeeded, $result->attemptStatus);
        $this->assertSame('settled', $result->providerStatus);
        $this->assertFalse($result->retryable);
        Http::assertSent(fn (Request $request): bool => $request['getTransactionDetailsRequest']['transId'] === 'private-provider-reference'
            && $request['getTransactionDetailsRequest']['merchantAuthentication']['name'] === 'sandbox-login'
        );
    }

    public function test_it_requires_the_operation_specific_authoritative_state(): void
    {
        $this->configureProvider();
        Http::fake([
            '*' => Http::response([
                'messages' => ['resultCode' => 'Ok'],
                'transaction' => ['transactionStatus' => 'capturedPendingSettlement'],
            ]),
        ]);

        $result = app(AuthorizeNetPaymentTransactionStatusGateway::class)
            ->status('provider-reference', PaymentOperation::Refund);

        $this->assertSame(PaymentAttemptStatus::Indeterminate, $result->attemptStatus);
        $this->assertSame('captured_pending_settlement', $result->providerStatus);
        $this->assertSame('provider_state_not_final', $result->safeErrorCode);
        $this->assertTrue($result->retryable);
    }

    private function configureProvider(): void
    {
        config()->set([
            'payments.authorize_net.api_login_id' => 'sandbox-login',
            'payments.authorize_net.transaction_key' => 'private-transaction-key',
            'payments.authorize_net.sandbox_endpoint' => 'https://apitest.authorize.net/xml/v1/request.api',
        ]);
    }
}
