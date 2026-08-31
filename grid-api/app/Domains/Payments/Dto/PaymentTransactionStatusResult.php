<?php

namespace App\Domains\Payments\Dto;

use App\Domains\Payments\Enums\PaymentAttemptStatus;

final readonly class PaymentTransactionStatusResult
{
    public function __construct(
        public PaymentAttemptStatus $attemptStatus,
        public string $providerStatus,
        public ?string $safeErrorCode = null,
        public bool $retryable = false,
    ) {}
}
