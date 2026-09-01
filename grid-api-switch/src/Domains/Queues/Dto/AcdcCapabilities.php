<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Queues\Dto;

final readonly class AcdcCapabilities
{
    public function __construct(
        public bool $configurationAvailable,
        public bool $liveAgentControlsAvailable,
        public bool $agentStatisticsAvailable,
        public bool $statisticsAvailable,
    ) {}

    /** @return array{configuration_available: bool, live_agent_controls_available: bool, agent_statistics_available: bool, statistics_available: bool} */
    public function toArray(): array
    {
        return [
            'configuration_available' => $this->configurationAvailable,
            'live_agent_controls_available' => $this->liveAgentControlsAvailable,
            'agent_statistics_available' => $this->agentStatisticsAvailable,
            'statistics_available' => $this->statisticsAvailable,
        ];
    }
}
