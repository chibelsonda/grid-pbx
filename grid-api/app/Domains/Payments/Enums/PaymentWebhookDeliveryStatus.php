<?php

namespace App\Domains\Payments\Enums;

enum PaymentWebhookDeliveryStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case RetryPending = 'retry_pending';
    case Failed = 'failed';
}
