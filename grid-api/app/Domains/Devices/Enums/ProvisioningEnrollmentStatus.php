<?php

namespace App\Domains\Devices\Enums;

enum ProvisioningEnrollmentStatus: string
{
    case NotEnrolled = 'not_enrolled';
    case Enrolled = 'enrolled';
}
