<?php

namespace App\Domains\SwitchSynchronization\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;

interface SwitchExtensionGateway
{
    /** @return iterable<int, array<string, mixed>> */
    public function users(SwitchAccount $account): iterable;

    /** @return iterable<int, array<string, mixed>> */
    public function devices(SwitchAccount $account): iterable;

    /** @return array<string, bool> */
    public function deviceStatuses(SwitchAccount $account): array;

    /** @return iterable<int, array<string, mixed>> */
    public function voicemailBoxes(SwitchAccount $account): iterable;

    /** @return iterable<int, array<string, mixed>> */
    public function voicemailMessages(SwitchAccount $account, string $voicemailBoxResourceId): iterable;

    /** @return array<string, mixed> */
    public function media(SwitchAccount $account, string $mediaResourceId): array;

    /** @return iterable<int, CallflowSnapshot> */
    public function callflows(SwitchAccount $account): iterable;
}
