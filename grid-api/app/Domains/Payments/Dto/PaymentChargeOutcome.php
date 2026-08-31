<?php

namespace App\Domains\Payments\Dto;

use App\Domains\Payments\Models\PaymentAttempt;

final readonly class PaymentChargeOutcome
{
    public function __construct(
        public PaymentAttempt $attempt,
        public bool $replayed,
    ) {}
}
