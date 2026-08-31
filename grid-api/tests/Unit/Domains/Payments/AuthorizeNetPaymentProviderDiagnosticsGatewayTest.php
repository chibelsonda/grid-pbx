<?php

namespace Tests\Unit\Domains\Payments;

use App\Domains\Payments\Contracts\PaymentProviderDiagnosticsGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthorizeNetPaymentProviderDiagnosticsGatewayTest extends TestCase
{
    public function test_it_authenticates_with_merchant_details_without_exposing_credentials(): void
    {
        config()->set([
            'payments.provider' => 'authorize_net',
            'payments.authorize_net.environment' => 'sandbox',
            'payments.authorize_net.api_login_id' => 'sandbox-login',
            'payments.authorize_net.transaction_key' => 'sandbox-transaction-key',
            'payments.authorize_net.public_client_key' => 'sandbox-public-key',
            'payments.authorize_net.sandbox_endpoint' => 'https://apitest.authorize.net/xml/v1/request.api',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://apitest.authorize.net/xml/v1/request.api' => Http::response(
                "\xEF\xBB\xBF".json_encode([
                    'merchantName' => 'Private merchant name',
                    'publicClientKey' => 'sandbox-public-key',
                    'messages' => [
                        'resultCode' => 'Ok',
                        'message' => [['code' => 'I00001', 'text' => 'Successful.']],
                    ],
                ], JSON_THROW_ON_ERROR),
            ),
        ]);

        $diagnostic = app(PaymentProviderDiagnosticsGateway::class)->inspect();

        $this->assertSame('ready', $diagnostic->status);
        $this->assertTrue($diagnostic->reachable);
        $this->assertTrue($diagnostic->authenticated);
        $this->assertTrue($diagnostic->publicClientKeyMatches);
        $this->assertStringNotContainsString('sandbox-login', json_encode($diagnostic->toSafeArray()));
        $this->assertStringNotContainsString('sandbox-transaction-key', json_encode($diagnostic->toSafeArray()));
        $this->assertStringNotContainsString('Private merchant name', json_encode($diagnostic->toSafeArray()));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://apitest.authorize.net/xml/v1/request.api'
            && $request['getMerchantDetailsRequest']['merchantAuthentication']['name'] === 'sandbox-login'
            && $request['getMerchantDetailsRequest']['merchantAuthentication']['transactionKey'] === 'sandbox-transaction-key'
        );
    }

    public function test_it_refuses_to_contact_production(): void
    {
        config()->set([
            'payments.provider' => 'authorize_net',
            'payments.authorize_net.environment' => 'production',
            'payments.authorize_net.api_login_id' => 'production-login',
            'payments.authorize_net.transaction_key' => 'production-key',
        ]);
        Http::preventStrayRequests();

        $diagnostic = app(PaymentProviderDiagnosticsGateway::class)->inspect();

        $this->assertSame('production_diagnostics_disabled', $diagnostic->status);
        $this->assertFalse($diagnostic->reachable);
        $this->assertFalse($diagnostic->authenticated);
        Http::assertNothingSent();
    }
}
