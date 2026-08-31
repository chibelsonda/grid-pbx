<?php

namespace App\Domains\Payments\Dto;

use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Models\PaymentCustomerProfile;

final readonly class PaymentProfileOutcome
{
    public function __construct(
        public PaymentAttempt $attempt,
        public ?PaymentCustomerProfile $profile,
        public bool $replayed,
    ) {}
}
