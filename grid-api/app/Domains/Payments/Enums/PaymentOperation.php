<?php

namespace App\Domains\Payments\Enums;

enum PaymentOperation: string
{
    case Charge = 'charge';
    case Refund = 'refund';
    case Void = 'void';
    case AttachPaymentMethod = 'attach_payment_method';
}
