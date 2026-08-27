<?php

namespace App\Domains\KazooSynchronization\Domain;

enum ProjectionStatus: string
{
    case Healthy = 'healthy';
    case Syncing = 'syncing';
    case Stale = 'stale';
    case Error = 'error';
}
