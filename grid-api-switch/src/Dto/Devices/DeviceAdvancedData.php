<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Devices;

final readonly class DeviceAdvancedData
{
    /** @param array<string, array{action: string}>|null $callRestrictions */
    public function __construct(
        public ?string $language = null,
        public ?string $timezone = null,
        public ?string $presenceId = null,
        public ?bool $doNotDisturb = null,
        public ?bool $callWaiting = null,
        public ?bool $excludeFromQueues = null,
        public ?bool $excludeFromContactList = null,
        public ?bool $mwiUnsolicitedUpdates = null,
        public ?bool $registerOverwriteNotify = null,
        public ?bool $suppressUnregisterNotifications = null,
        public ?string $outboundPrivacy = null,
        public ?string $internalRingtone = null,
        public ?string $externalRingtone = null,
        public ?array $callRestrictions = null,
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = array_filter([
            'language' => $this->language,
            'timezone' => $this->timezone,
            'presence_id' => $this->presenceId,
            'exclude_from_queues' => $this->excludeFromQueues,
            'mwi_unsolicited_updates' => $this->mwiUnsolicitedUpdates,
            'register_overwrite_notify' => $this->registerOverwriteNotify,
            'suppress_unregister_notifications' => $this->suppressUnregisterNotifications,
            'call_restriction' => $this->callRestrictions,
        ], static fn (mixed $value): bool => $value !== null);

        if ($this->doNotDisturb !== null) {
            $data['do_not_disturb'] = ['enabled' => $this->doNotDisturb];
        }

        if ($this->callWaiting !== null) {
            $data['call_waiting'] = ['enabled' => $this->callWaiting];
        }

        if ($this->excludeFromContactList !== null) {
            $data['contact_list'] = ['exclude' => $this->excludeFromContactList];
        }

        if ($this->outboundPrivacy !== null) {
            $data['caller_id_options'] = ['outbound_privacy' => $this->outboundPrivacy];
        }

        $ringtones = array_filter([
            'internal' => $this->internalRingtone,
            'external' => $this->externalRingtone,
        ], static fn (?string $value): bool => $value !== null);

        if ($ringtones !== []) {
            $data['ringtones'] = $ringtones;
        }

        return $data;
    }
}
