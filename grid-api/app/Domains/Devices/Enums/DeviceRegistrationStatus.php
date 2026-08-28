<?php

namespace App\Domains\Devices\Enums;

enum DeviceRegistrationStatus: string
{
    case Registered = 'registered';
    case Unregistered = 'unregistered';
    case Unknown = 'unknown';
}
