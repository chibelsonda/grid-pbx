<?php

namespace App\Domains\Payments\Dto;

use App\Domains\Payments\Enums\PaymentAttemptStatus;

final readonly class PaymentProfileResult
{
    public function __construct(
        public PaymentAttemptStatus $status,
        public ?string $providerCustomerProfileId = null,
        public ?string $providerPaymentProfileId = null,
        public ?string $safeErrorCode = null,
    ) {}
}
