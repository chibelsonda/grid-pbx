<?php

namespace App\Domains\Payments\Dto;

use App\Domains\Payments\Enums\PaymentAttemptStatus;

final readonly class PaymentMutationResult
{
    public function __construct(
        public PaymentAttemptStatus $status,
        public ?string $providerReference = null,
        public ?string $safeErrorCode = null,
    ) {}
}
