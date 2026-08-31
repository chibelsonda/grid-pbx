<?php

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Dto\PaymentMutationResult;

interface PaymentReversalGateway
{
    public function void(string $providerReference, string $attemptId): PaymentMutationResult;

    public function refund(
        string $providerReference,
        int $amountMinor,
        string $currency,
        string $attemptId,
    ): PaymentMutationResult;
}
