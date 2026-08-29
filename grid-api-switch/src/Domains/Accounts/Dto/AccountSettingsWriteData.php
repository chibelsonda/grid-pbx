<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

final readonly class AccountSettingsWriteData
{
    public function __construct(
        public string $name,
        public ?string $organizationName,
        public ?string $timezone,
        public ?string $language,
        public bool $callWaitingEnabled,
        public bool $doNotDisturbEnabled,
        public string $outboundPrivacy,
        public bool $showRate,
        public ?string $internalRingtone,
        public ?string $externalRingtone,
        public AccountCallerIdWriteData $callerId,
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return [
            'name' => $this->name,
            'org' => $this->organizationName ?? '',
            'timezone' => $this->timezone ?? '',
            'language' => $this->language ?? '',
            'call_waiting' => ['enabled' => $this->callWaitingEnabled],
            'do_not_disturb' => ['enabled' => $this->doNotDisturbEnabled],
            'caller_id' => $this->callerId->toSwitchData(),
            'caller_id_options' => [
                'outbound_privacy' => $this->outboundPrivacy,
                'show_rate' => $this->showRate,
            ],
            'ringtones' => [
                'internal' => $this->internalRingtone ?? '',
                'external' => $this->externalRingtone ?? '',
            ],
        ];
    }
}
