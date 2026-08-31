<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Payments\Contracts\PaymentReversalGateway;
use App\Domains\Payments\Dto\PaymentMutationResult;
use App\Domains\Payments\Exceptions\PaymentMutationUnavailableException;

final class UnavailablePaymentReversalGateway implements PaymentReversalGateway
{
    public function void(string $providerReference, string $attemptId): PaymentMutationResult
    {
        throw new PaymentMutationUnavailableException;
    }

    public function refund(
        string $providerReference,
        int $amountMinor,
        string $currency,
        string $attemptId,
    ): PaymentMutationResult {
        throw new PaymentMutationUnavailableException;
    }
}
