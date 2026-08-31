<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Payments\Contracts\PaymentProviderDiagnosticsGateway;
use App\Domains\Payments\Dto\PaymentProviderDiagnostic;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Throwable;

final class AuthorizeNetPaymentProviderDiagnosticsGateway implements PaymentProviderDiagnosticsGateway
{
    public function __construct(private readonly Factory $http) {}

    public function inspect(): PaymentProviderDiagnostic
    {
        $environment = strtolower(trim((string) config('payments.authorize_net.environment')));
        $loginId = trim((string) config('payments.authorize_net.api_login_id'));
        $transactionKey = trim((string) config('payments.authorize_net.transaction_key'));
        $configuredClientKey = trim((string) config('payments.authorize_net.public_client_key'));
        $configured = $loginId !== '' && $transactionKey !== '';

        if ($environment !== 'sandbox') {
            return $this->result($environment, $configured, status: 'production_diagnostics_disabled');
        }

        if (! $configured) {
            return $this->result($environment, false, status: 'not_configured');
        }

        try {
            $response = $this->http
                ->acceptJson()
                ->asJson()
                ->connectTimeout((int) config('payments.authorize_net.connect_timeout', 5))
                ->timeout((int) config('payments.authorize_net.timeout', 10))
                ->post((string) config('payments.authorize_net.sandbox_endpoint'), [
                    'getMerchantDetailsRequest' => [
                        'merchantAuthentication' => [
                            'name' => $loginId,
                            'transactionKey' => $transactionKey,
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            return $this->result($environment, true, status: 'unreachable');
        } catch (Throwable) {
            return $this->result($environment, true, status: 'request_failed');
        }

        $payload = $this->decode($response->body());

        if (! is_array($payload)) {
            return $this->result(
                $environment,
                true,
                reachable: true,
                status: 'invalid_response',
            );
        }

        $authenticated = $response->successful()
            && data_get($payload, 'messages.resultCode') === 'Ok';

        if (! $authenticated) {
            return $this->result(
                $environment,
                true,
                reachable: true,
                status: 'credentials_rejected',
            );
        }

        $returnedClientKey = data_get($payload, 'publicClientKey');
        $clientKeyMatches = $configuredClientKey === '' || ! is_string($returnedClientKey)
            ? null
            : hash_equals($configuredClientKey, $returnedClientKey);

        return $this->result(
            $environment,
            true,
            reachable: true,
            authenticated: true,
            publicClientKeyMatches: $clientKeyMatches,
            status: 'ready',
        );
    }

    /** @return array<string, mixed>|null */
    private function decode(string $body): ?array
    {
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function result(
        string $environment,
        bool $configured,
        bool $reachable = false,
        bool $authenticated = false,
        ?bool $publicClientKeyMatches = null,
        string $status = 'unavailable',
    ): PaymentProviderDiagnostic {
        return new PaymentProviderDiagnostic(
            provider: 'authorize_net',
            environment: $environment,
            configured: $configured,
            reachable: $reachable,
            authenticated: $authenticated,
            publicClientKeyMatches: $publicClientKeyMatches,
            status: $status,
        );
    }
}
