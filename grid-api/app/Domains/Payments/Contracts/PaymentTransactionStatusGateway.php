<?php

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Dto\PaymentTransactionStatusResult;
use App\Domains\Payments\Enums\PaymentOperation;

interface PaymentTransactionStatusGateway
{
    public function status(
        string $providerReference,
        PaymentOperation $operation,
    ): PaymentTransactionStatusResult;
}
