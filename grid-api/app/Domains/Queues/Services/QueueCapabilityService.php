<?php

namespace App\Domains\Queues\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchQueueGateway;
use Illuminate\Support\Facades\Cache;

class QueueCapabilityService
{
    public function __construct(private readonly SwitchQueueGateway $gateway) {}

    /** @return array{configuration_available: bool, live_agent_controls_available: bool, agent_statistics_available: bool, statistics_available: bool} */
    public function get(SwitchAccount $account): array
    {
        return Cache::remember(
            "switch:queue-capabilities:{$account->id}",
            now()->addMinute(),
            fn (): array => $this->gateway->capabilities($account),
        );
    }
}
