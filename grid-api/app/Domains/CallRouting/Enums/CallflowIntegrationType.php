<?php

namespace App\Domains\CallRouting\Enums;

enum CallflowIntegrationType: string
{
    case Pivot = 'pivot';
    case Webhook = 'webhook';
    case Disa = 'disa';
    case GlobalCarrier = 'global_carrier';
    case AccountCarrier = 'account_carrier';
}
