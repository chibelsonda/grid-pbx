<?php

namespace App\Domains\Payments\Dto;

final readonly class PaymentProfileCommand
{
    public function __construct(public string $idempotencyKey) {}
}
