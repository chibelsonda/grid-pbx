<?php

namespace App\Domains\Devices\Services;

use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use Throwable;

class DeviceSchemaCompatibilityService
{
    public function __construct(private readonly SwitchDeviceGateway $gateway) {}

    /** @return array<string, mixed> */
    public function current(): array
    {
        try {
            return $this->gateway->schemaCompatibility();
        } catch (Throwable) {
            return $this->legacyFallback();
        }
    }

    /** @return array<string, mixed> */
    private function legacyFallback(): array
    {
        return [
            'source' => 'bundled_legacy_fallback',
            'schema_id' => 'devices',
            'call_forward' => ['number_max_length' => 15],
            'sip' => [
                'invite_formats' => ['username', 'npan', '1npan', 'e164', 'route', 'contact'],
                'custom_sip_interface' => false,
                'forward' => false,
                'proxy' => false,
                'static_invite' => false,
                'transport' => false,
            ],
            'provision' => [
                'template_id' => false,
                'endpoint_model_types' => ['string', 'integer'],
                'check_sync_event' => true,
                'check_sync_reload' => true,
                'check_sync_reboot' => true,
            ],
        ];
    }
}
