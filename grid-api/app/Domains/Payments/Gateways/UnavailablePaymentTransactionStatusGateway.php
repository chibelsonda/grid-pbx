<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Payments\Contracts\PaymentTransactionStatusGateway;
use App\Domains\Payments\Dto\PaymentTransactionStatusResult;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;

final class UnavailablePaymentTransactionStatusGateway implements PaymentTransactionStatusGateway
{
    public function status(
        string $providerReference,
        PaymentOperation $operation,
    ): PaymentTransactionStatusResult {
        return new PaymentTransactionStatusResult(
            PaymentAttemptStatus::Indeterminate,
            'unavailable',
            'provider_unavailable',
            true,
        );
    }
}
