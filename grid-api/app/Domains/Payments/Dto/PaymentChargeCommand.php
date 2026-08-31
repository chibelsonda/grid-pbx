<?php

namespace App\Domains\Payments\Dto;

final readonly class PaymentChargeCommand
{
    public function __construct(
        public string $idempotencyKey,
        public int $amountMinor,
        public string $currency,
        public string $dataDescriptor,
        public string $dataValue,
    ) {}
}
