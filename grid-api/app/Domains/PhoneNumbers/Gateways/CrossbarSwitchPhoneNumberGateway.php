<?php

namespace App\Domains\PhoneNumbers\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Contracts\SwitchPhoneNumberGateway;
use Generator;
use GridPbx\Switch\Domains\PhoneNumbers\PhoneNumberResourceClient;

class CrossbarSwitchPhoneNumberGateway implements SwitchPhoneNumberGateway
{
    public function __construct(private readonly PhoneNumberResourceClient $phoneNumbers) {}

    public function all(SwitchAccount $account): Generator
    {
        foreach ($this->phoneNumbers->allDetails($account->switch_account_id) as $snapshot) {
            yield $snapshot->toArray();
        }
    }
}
