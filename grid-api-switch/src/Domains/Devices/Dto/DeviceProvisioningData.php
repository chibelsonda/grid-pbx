<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

final readonly class DeviceProvisioningData
{
    public function __construct(
        public ?string $checkSyncEvent = null,
        public ?string $checkSyncReload = null,
        public ?string $checkSyncReboot = null,
        public ?string $templateId = null,
    ) {}

    /** @return array<string, string> */
    public function toSwitchData(): array
    {
        return array_filter([
            'id' => $this->templateId,
            'check_sync_event' => $this->checkSyncEvent,
            'check_sync_reload' => $this->checkSyncReload,
            'check_sync_reboot' => $this->checkSyncReboot,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
