<?php

namespace App\Domains\Queues\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchAgentGateway;
use GridPbx\Switch\Domains\Agents\AgentResourceClient;
use GridPbx\Switch\Domains\Agents\Dto\AgentQueueMembershipWriteData;
use GridPbx\Switch\Domains\Agents\Dto\AgentStatusWriteData;

class CrossbarSwitchAgentGateway implements SwitchAgentGateway
{
    public function __construct(private readonly AgentResourceClient $agents) {}

    public function availability(SwitchAccount $account): array
    {
        return $this->agents->availability($account->switch_account_id)->toArray();
    }

    public function queueIds(SwitchAccount $account, string $switchUserId): array
    {
        return $this->agents->queueIds($account->switch_account_id, $switchUserId);
    }

    public function statistics(SwitchAccount $account): array
    {
        return $this->agents->statistics($account->switch_account_id)->toArray();
    }

    public function status(SwitchAccount $account, string $switchUserId): array
    {
        return $this->agents->status($account->switch_account_id, $switchUserId)->data;
    }

    public function updateStatus(SwitchAccount $account, string $switchUserId, string $status, ?int $pauseTimeout): void
    {
        $this->agents->updateStatus($account->switch_account_id, $switchUserId, new AgentStatusWriteData($status, $pauseTimeout));
    }

    public function updateQueueMembership(SwitchAccount $account, string $switchUserId, string $action, string $switchQueueId): array
    {
        return $this->agents->updateQueueMembership(
            $account->switch_account_id,
            $switchUserId,
            new AgentQueueMembershipWriteData($action, $switchQueueId),
        );
    }
}
