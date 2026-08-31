<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Payments\Contracts\PaymentProfileGateway;
use App\Domains\Payments\Dto\PaymentProfileResult;
use App\Domains\Payments\Exceptions\PaymentMutationUnavailableException;

final class UnavailablePaymentProfileGateway implements PaymentProfileGateway
{
    public function createFromTransaction(
        string $providerReference,
        string $merchantCustomerId,
        string $description,
        ?string $email,
    ): PaymentProfileResult {
        throw new PaymentMutationUnavailableException;
    }
}
