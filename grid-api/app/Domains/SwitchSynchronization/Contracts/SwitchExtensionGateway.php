<?php

namespace App\Domains\SwitchSynchronization\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchExtensionGateway
{
    /** @return iterable<int, array<string, mixed>> */
    public function users(SwitchAccount $account): iterable;

    /** @return iterable<int, array<string, mixed>> */
    public function devices(SwitchAccount $account): iterable;

    /** @return iterable<int, array<string, mixed>> */
    public function voicemailBoxes(SwitchAccount $account): iterable;

    /** @return iterable<int, array<string, mixed>> */
    public function callflows(SwitchAccount $account): iterable;
}
