<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Payments\Contracts\PaymentProviderDiagnosticsGateway;
use App\Domains\Payments\Dto\PaymentProviderDiagnostic;

final class UnavailablePaymentProviderDiagnosticsGateway implements PaymentProviderDiagnosticsGateway
{
    public function inspect(): PaymentProviderDiagnostic
    {
        return new PaymentProviderDiagnostic(
            provider: (string) config('payments.provider', 'unavailable'),
            environment: 'unsupported',
            configured: false,
            reachable: false,
            authenticated: false,
            publicClientKeyMatches: null,
            status: 'unsupported_provider',
        );
    }
}
