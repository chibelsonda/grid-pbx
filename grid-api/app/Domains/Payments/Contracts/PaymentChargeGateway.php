<?php

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Dto\PaymentChargeCommand;
use App\Domains\Payments\Dto\PaymentMutationResult;

interface PaymentChargeGateway
{
    public function charge(PaymentChargeCommand $command, string $attemptId): PaymentMutationResult;
}
