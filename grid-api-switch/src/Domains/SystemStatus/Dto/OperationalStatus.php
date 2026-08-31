<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\SystemStatus\Dto;

final readonly class OperationalStatus
{
    public function __construct(
        public bool $presenceSubscriptionDiagnosticsAvailable,
        public bool $parkedCallSummaryAvailable,
        public ?int $activeParkedCallCount,
        public bool $webhookEventCatalogAvailable,
        public ?int $webhookAvailableEventCount,
        public bool $webhookConfigurationSummaryAvailable,
        public ?int $webhookConfiguredCount,
        public ?int $webhookEnabledCount,
        public bool $smsInventoryAvailable,
        public bool $mmsInventoryAvailable,
        public bool $portRequestInventoryAvailable,
        public bool $numberCarrierConfigurationAvailable,
    ) {}

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
    public function toArray(): array
    {
        return [
            'presence_subscription_diagnostics_available' => $this->presenceSubscriptionDiagnosticsAvailable,
            'parked_call_summary_available' => $this->parkedCallSummaryAvailable,
            'active_parked_call_count' => $this->activeParkedCallCount,
            'webhook_event_catalog_available' => $this->webhookEventCatalogAvailable,
            'webhook_available_event_count' => $this->webhookAvailableEventCount,
            'webhook_configuration_summary_available' => $this->webhookConfigurationSummaryAvailable,
            'webhook_configured_count' => $this->webhookConfiguredCount,
            'webhook_enabled_count' => $this->webhookEnabledCount,
            'sms_inventory_available' => $this->smsInventoryAvailable,
            'mms_inventory_available' => $this->mmsInventoryAvailable,
            'port_request_inventory_available' => $this->portRequestInventoryAvailable,
            'number_carrier_configuration_available' => $this->numberCarrierConfigurationAvailable,
        ];
    }
}
