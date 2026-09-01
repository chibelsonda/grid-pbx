<?php

namespace App\Domains\Queues\Services;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchAgentGateway;
use App\Domains\Queues\Exceptions\AgentAvailabilityUnavailableException;

class AgentAvailabilityService
{
    public function __construct(
        private readonly SwitchAgentGateway $gateway,
        private readonly QueueCapabilityService $capabilities,
    ) {}

    /**
     * Resolve private Switch Agent identifiers and discard call/Queue details.
     *
     * @return array{
     *   observed_at: string,
     *   agents: list<array{id: string, status: string, changed_at: int}>,
     *   unresolved_agents: int
     * }
     */
    public function get(SwitchAccount $account): array
    {
        if (! $this->capabilities->get($account)['live_agent_controls_available']) {
            throw new AgentAvailabilityUnavailableException;
        }

        $availability = collect($this->gateway->availability($account));
        $availabilityByAgent = $availability->keyBy('agent_id');
        $agents = $account->extensions()
            ->whereHas('queueMemberships')
            ->get(['extension_id', 'id', 'switch_resource_id'])
            ->keyBy('switch_resource_id');

        return [
            'observed_at' => now()->toIso8601String(),
            'agents' => $agents->map(function (SwitchExtension $agent) use ($availabilityByAgent): array {
                $state = $availabilityByAgent->get($agent->switch_resource_id);

                return [
                    'id' => $agent->id,
                    'status' => is_array($state) ? $state['status'] : 'unknown',
                    'changed_at' => is_array($state) ? $state['timestamp'] : 0,
                ];
            })->values()->all(),
            'unresolved_agents' => $availability->filter(
                static fn (array $state): bool => ! $agents->has($state['agent_id']),
            )->count(),
        ];
    }
}
