<?php

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Enums\PaymentOperation;

final class PaymentWebhookEventPolicy
{
    public function operation(string $eventType): ?PaymentOperation
    {
        return match ($eventType) {
            'net.authorize.payment.authorization.created',
            'net.authorize.payment.authcapture.created',
            'net.authorize.payment.capture.created',
            'net.authorize.payment.priorAuthCapture.created' => PaymentOperation::Charge,
            'net.authorize.payment.refund.created' => PaymentOperation::Refund,
            'net.authorize.payment.void.created' => PaymentOperation::Void,
            default => null,
        };
    }
}
