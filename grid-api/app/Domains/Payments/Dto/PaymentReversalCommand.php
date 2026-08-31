<?php

namespace App\Domains\Payments\Dto;

use App\Domains\Payments\Enums\PaymentOperation;

final readonly class PaymentReversalCommand
{
    public function __construct(
        public string $idempotencyKey,
        public PaymentOperation $operation,
        public ?int $amountMinor,
        public string $currency,
    ) {}
}
