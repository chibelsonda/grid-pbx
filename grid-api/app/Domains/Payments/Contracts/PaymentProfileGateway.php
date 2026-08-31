<?php

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Dto\PaymentProfileResult;

interface PaymentProfileGateway
{
    public function createFromTransaction(
        string $providerReference,
        string $merchantCustomerId,
        string $description,
        ?string $email,
    ): PaymentProfileResult;
}
