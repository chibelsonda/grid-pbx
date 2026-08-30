<?php

namespace App\Domains\SystemStatus\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SystemStatus\Contracts\SwitchOperationalStatusGateway;
use Illuminate\Support\Facades\Cache;

class OperationalStatusService
{
    public function __construct(private readonly SwitchOperationalStatusGateway $gateway) {}

    /**
     * @return array{
     *     observed_at: string,
     *     presence: array{subscription_diagnostics_available: bool, live_status_available: false, commands_available: false},
     *     parking: array{summary_available: bool, active_call_count: int|null, actions_available: false}
     * }
     */
    public function get(SwitchAccount $account): array
    {
        return Cache::remember(
            "switch:operational-status:{$account->id}",
            now()->addSeconds(10),
            function () use ($account): array {
                $status = $this->gateway->inspect($account);

                return [
                    'observed_at' => now()->toIso8601String(),
                    'presence' => [
                        'subscription_diagnostics_available' => $status['presence_subscription_diagnostics_available'],
                        'live_status_available' => false,
                        'commands_available' => false,
                    ],
                    'parking' => [
                        'summary_available' => $status['parked_call_summary_available'],
                        'active_call_count' => $status['active_parked_call_count'],
                        'actions_available' => false,
                    ],
                ];
            },
        );
    }
}
