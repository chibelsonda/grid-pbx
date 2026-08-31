<?php

namespace App\Domains\Payments\Exceptions;

use RuntimeException;

class PaymentWebhookRetryException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Payment webhook reconciliation is pending.');
    }
}
