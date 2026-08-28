<?php

namespace App\Domains\SwitchSynchronization\Enums;

enum ProjectionStatus: string
{
    case Healthy = 'healthy';
    case Syncing = 'syncing';
    case Stale = 'stale';
    case Error = 'error';
}
