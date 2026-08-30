<?php

namespace App\Domains\SystemStatus\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SystemStatus\Contracts\SwitchOperationalStatusGateway;
use GridPbx\Switch\Domains\SystemStatus\OperationalStatusClient;

class CrossbarSwitchOperationalStatusGateway implements SwitchOperationalStatusGateway
{
    public function __construct(private readonly OperationalStatusClient $client) {}

    public function inspect(SwitchAccount $account): array
    {
        return $this->client->inspect($account->switch_account_id)->toArray();
    }
}
