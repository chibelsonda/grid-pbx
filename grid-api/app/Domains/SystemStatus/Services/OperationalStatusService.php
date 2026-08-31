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
     *     parking: array{summary_available: bool, active_call_count: int|null, actions_available: false},
     *     webhooks: array{event_catalog_available: bool, available_event_count: int|null, configuration_summary_available: bool, configured_count: int|null, enabled_count: int|null, configuration_mutations_available: false, delivery_history_available: false},
     *     messaging: array{sms_inventory_available: bool, mms_inventory_available: bool, message_content_available: false, sending_available: false},
     *     number_porting: array{inventory_available: bool, request_details_available: false, documents_available: false, workflow_mutations_available: false},
     *     number_management: array{carrier_configuration_available: bool, search_available: false, purchase_available: false, reservation_available: false, release_available: false}
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
                    'webhooks' => [
                        'event_catalog_available' => $status['webhook_event_catalog_available'],
                        'available_event_count' => $status['webhook_available_event_count'],
                        'configuration_summary_available' => $status['webhook_configuration_summary_available'],
                        'configured_count' => $status['webhook_configured_count'],
                        'enabled_count' => $status['webhook_enabled_count'],
                        'configuration_mutations_available' => false,
                        'delivery_history_available' => false,
                    ],
                    'messaging' => [
                        'sms_inventory_available' => $status['sms_inventory_available'],
                        'mms_inventory_available' => $status['mms_inventory_available'],
                        'message_content_available' => false,
                        'sending_available' => false,
                    ],
                    'number_porting' => [
                        'inventory_available' => $status['port_request_inventory_available'],
                        'request_details_available' => false,
                        'documents_available' => false,
                        'workflow_mutations_available' => false,
                    ],
                    'number_management' => [
                        'carrier_configuration_available' => $status['number_carrier_configuration_available'],
                        'search_available' => false,
                        'purchase_available' => false,
                        'reservation_available' => false,
                        'release_available' => false,
                    ],
                ];
            },
        );
    }
}
