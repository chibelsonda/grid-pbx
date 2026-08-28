<?php

namespace App\Domains\Queues\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchAgentGateway;
use GridPbx\Switch\Dto\Agents\AgentStatusWriteData;
use GridPbx\Switch\Resources\AgentResourceClient;

class CrossbarSwitchAgentGateway implements SwitchAgentGateway
{
    public function __construct(private readonly AgentResourceClient $agents) {}

    public function status(SwitchAccount $account, string $switchUserId): array
    {
        return $this->agents->status($account->switch_account_id, $switchUserId)->data;
    }

    public function updateStatus(SwitchAccount $account, string $switchUserId, string $status, ?int $pauseTimeout): void
    {
        $this->agents->updateStatus($account->switch_account_id, $switchUserId, new AgentStatusWriteData($status, $pauseTimeout));
    }
}
