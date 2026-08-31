<?php

namespace App\Domains\Payments\Enums;

enum PaymentAttemptStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Indeterminate = 'indeterminate';
    case Cancelled = 'cancelled';
}
