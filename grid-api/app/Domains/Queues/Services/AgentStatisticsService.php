<?php

namespace App\Domains\Queues\Services;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchAgentGateway;
use App\Domains\Queues\Exceptions\AgentStatisticsUnavailableException;
use Illuminate\Support\Collection;

class AgentStatisticsService
{
    public function __construct(
        private readonly SwitchAgentGateway $gateway,
        private readonly QueueCapabilityService $capabilities,
    ) {}

    /**
     * Resolve private Switch identifiers before returning aggregate performance data.
     *
     * @return array{
     *   observed_at: string,
     *   totals: array{total_calls: int, answered_calls: int, missed_calls: int, answer_rate_percentage: float|null},
     *   agents: list<array{id: string, name: string, extension: string|null, total_calls: int, answered_calls: int, missed_calls: int, answer_rate_percentage: float|null}>,
     *   unresolved_agents: int
     * }
     */
    public function get(SwitchAccount $account): array
    {
        if (! $this->capabilities->get($account)['agent_statistics_available']) {
            throw new AgentStatisticsUnavailableException;
        }

        $statistics = collect($this->gateway->statistics($account));
        $statisticsByAgent = $statistics->keyBy('agent_id');
        $agents = $account->extensions()
            ->whereHas('queueMemberships')
            ->orderBy('display_name')
            ->orderBy('extension')
            ->get(['extension_id', 'id', 'switch_resource_id', 'display_name', 'extension'])
            ->keyBy('switch_resource_id');
        $unresolved = $statistics->filter(
            static fn (array $statistic): bool => ! $agents->has($statistic['agent_id']),
        )->count();

        return [
            'observed_at' => now()->toIso8601String(),
            'totals' => $this->metrics($statistics),
            'agents' => $agents->map(function (SwitchExtension $agent) use ($statisticsByAgent): array {
                $statistic = $statisticsByAgent->get($agent->switch_resource_id);

                return [
                    'id' => $agent->id,
                    'name' => $agent->display_name ?? $agent->extension ?? 'Unnamed agent',
                    'extension' => $agent->extension,
                    ...$this->metrics($statistic === null ? collect() : collect([$statistic])),
                ];
            })->values()->all(),
            'unresolved_agents' => $unresolved,
        ];
    }

    /**
     * @param  Collection<int|string, array{agent_id: string, total_calls: int, answered_calls: int, missed_calls: int}>  $statistics
     * @return array{total_calls: int, answered_calls: int, missed_calls: int, answer_rate_percentage: float|null}
     */
    private function metrics(Collection $statistics): array
    {
        $totalCalls = (int) $statistics->sum('total_calls');
        $answeredCalls = (int) $statistics->sum('answered_calls');

        return [
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'missed_calls' => (int) $statistics->sum('missed_calls'),
            'answer_rate_percentage' => $totalCalls === 0
                ? null
                : round(($answeredCalls / $totalCalls) * 100, 1),
        ];
    }
}
