<?php

namespace App\Domains\Devices\Services;

class StarterDevicePolicy
{
    /** @var list<string> */
    public const SUPPORTED_TYPES = ['sip_device', 'smartphone', 'softphone', 'fax', 'ata'];

    /** @var list<string> */
    public const PROVISIONABLE_TYPES = ['sip_device', 'fax', 'ata'];

    /** @return array<string, list<string>> */
    public function capabilities(): array
    {
        return [
            'supported_types' => self::SUPPORTED_TYPES,
            'provisionable_types' => self::PROVISIONABLE_TYPES,
            'sip_credential_types' => self::SUPPORTED_TYPES,
        ];
    }

    public function isProvisionable(?string $deviceType): bool
    {
        return in_array($deviceType, self::PROVISIONABLE_TYPES, true);
    }
}
