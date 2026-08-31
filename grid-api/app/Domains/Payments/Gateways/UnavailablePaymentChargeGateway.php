<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Payments\Contracts\PaymentChargeGateway;
use App\Domains\Payments\Dto\PaymentChargeCommand;
use App\Domains\Payments\Dto\PaymentMutationResult;
use App\Domains\Payments\Exceptions\PaymentMutationUnavailableException;

final class UnavailablePaymentChargeGateway implements PaymentChargeGateway
{
    public function charge(PaymentChargeCommand $command, string $attemptId): PaymentMutationResult
    {
        throw new PaymentMutationUnavailableException;
    }
}
