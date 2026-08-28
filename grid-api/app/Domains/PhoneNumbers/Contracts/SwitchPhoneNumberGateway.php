<?php

namespace App\Domains\PhoneNumbers\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use Generator;

interface SwitchPhoneNumberGateway
{
    /** @return Generator<int, array<string, mixed>> */
    public function all(SwitchAccount $account): Generator;
}
