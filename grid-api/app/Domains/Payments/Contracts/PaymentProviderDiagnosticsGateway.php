<?php

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Dto\PaymentProviderDiagnostic;

interface PaymentProviderDiagnosticsGateway
{
    public function inspect(): PaymentProviderDiagnostic;
}
