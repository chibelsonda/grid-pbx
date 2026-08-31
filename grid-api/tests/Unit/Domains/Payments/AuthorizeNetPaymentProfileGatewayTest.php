<?php

namespace Tests\Unit\Domains\Payments;

use App\Domains\Payments\Contracts\PaymentProfileGateway;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthorizeNetPaymentProfileGatewayTest extends TestCase
{
    public function test_it_creates_a_customer_profile_from_a_server_resolved_transaction(): void
    {
        config()->set([
            'payments.provider' => 'authorize_net',
            'payments.authorize_net.environment' => 'sandbox',
            'payments.authorize_net.api_login_id' => 'sandbox-login',
            'payments.authorize_net.transaction_key' => 'sandbox-transaction-key',
            'payments.authorize_net.sandbox_endpoint' => 'https://apitest.authorize.net/xml/v1/request.api',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://apitest.authorize.net/xml/v1/request.api' => Http::response([
                'messages' => ['resultCode' => 'Ok'],
                'customerProfileId' => 'private-customer-profile-id',
                'customerPaymentProfileIdList' => [
                    'numericString' => ['private-payment-profile-id'],
                ],
            ]),
        ]);

        $result = app(PaymentProfileGateway::class)->createFromTransaction(
            'private-source-transaction',
            'merchant-customer-id',
            'GridPBX account',
            'admin@example.test',
        );

        $this->assertSame(PaymentAttemptStatus::Succeeded, $result->status);
        $this->assertSame('private-customer-profile-id', $result->providerCustomerProfileId);
        $this->assertSame('private-payment-profile-id', $result->providerPaymentProfileId);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $serialized = json_encode($payload, JSON_THROW_ON_ERROR);

            return data_get($payload, 'createCustomerProfileFromTransactionRequest.transId') === 'private-source-transaction'
                && data_get($payload, 'createCustomerProfileFromTransactionRequest.customer.merchantCustomerId') === 'merchant-customer-id'
                && ! str_contains($serialized, 'cardNumber')
                && ! str_contains($serialized, 'cardCode');
        });
    }
}
