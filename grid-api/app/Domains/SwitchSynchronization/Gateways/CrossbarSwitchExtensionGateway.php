<?php

namespace App\Domains\SwitchSynchronization\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Contracts\SwitchExtensionGateway;
use Generator;
use GridPbx\Switch\Resources\AccountResource;
use GridPbx\Switch\Resources\AccountResourceClient;

class CrossbarSwitchExtensionGateway implements SwitchExtensionGateway
{
    public function __construct(private readonly AccountResourceClient $resources) {}

    /** @return Generator<int, array<string, mixed>> */
    public function users(SwitchAccount $account): Generator
    {
        yield from $this->details($account, AccountResource::Users);
    }

    /** @return Generator<int, array<string, mixed>> */
    public function devices(SwitchAccount $account): Generator
    {
        yield from $this->details($account, AccountResource::Devices);
    }

    /** @return Generator<int, array<string, mixed>> */
    public function voicemailBoxes(SwitchAccount $account): Generator
    {
        yield from $this->details($account, AccountResource::VoicemailBoxes);
    }

    /** @return Generator<int, array<string, mixed>> */
    public function callflows(SwitchAccount $account): Generator
    {
        yield from $this->details($account, AccountResource::Callflows);
    }

    /** @return Generator<int, array<string, mixed>> */
    private function details(SwitchAccount $account, AccountResource $resource): Generator
    {
        foreach ($this->resources->allDetails($account->switch_account_id, $resource) as $snapshot) {
            yield $snapshot->toArray();
        }
    }
}
