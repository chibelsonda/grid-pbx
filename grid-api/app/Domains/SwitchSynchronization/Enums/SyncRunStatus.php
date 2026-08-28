<?php

namespace App\Domains\SwitchSynchronization\Enums;

enum SyncRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
