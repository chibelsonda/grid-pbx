<?php

namespace App\Domains\Payments\Dto;

use App\Domains\Payments\Models\PaymentWebhookDelivery;

final readonly class PaymentWebhookReceipt
{
    public function __construct(
        public PaymentWebhookDelivery $delivery,
        public bool $replayed,
    ) {}
}
