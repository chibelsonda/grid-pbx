<?php

namespace App\Domains\SystemStatus\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchOperationalStatusGateway
{
    /**
     * @return array{
     *     presence_subscription_diagnostics_available: bool,
     *     parked_call_summary_available: bool,
     *     active_parked_call_count: int|null,
     *     webhook_event_catalog_available: bool,
     *     webhook_available_event_count: int|null,
     *     webhook_configuration_summary_available: bool,
     *     webhook_configured_count: int|null,
     *     webhook_enabled_count: int|null,
     *     sms_inventory_available: bool,
     *     mms_inventory_available: bool,
     *     port_request_inventory_available: bool,
     *     number_carrier_configuration_available: bool
     * }
     */
    public function inspect(SwitchAccount $account): array;
}
