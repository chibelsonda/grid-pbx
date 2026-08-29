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
        public ?AccountCallRestrictionsData $callRestrictions = null,
        public ?AccountCallRecordingData $callRecording = null,
        public ?AccountDialPlanData $dialPlan = null,
        public ?AccountFormattersData $formatters = null,
        public ?AccountPreflowData $preflow = null,
        public ?AccountMetaflowsData $metaflows = null,
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = [
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

        if ($this->callRestrictions !== null) {
            $data['call_restriction'] = $this->callRestrictions->toSwitchData();
        }

        if ($this->callRecording !== null) {
            $data['call_recording'] = $this->callRecording->toSwitchData();
        }

        if ($this->dialPlan !== null) {
            $data['dial_plan'] = $this->dialPlan->toSwitchData();
        }

        if ($this->formatters !== null) {
            $data['formatters'] = $this->formatters->toSwitchData();
        }

        if ($this->preflow !== null) {
            $data['preflow'] = $this->preflow->toSwitchData();
        }

        if ($this->metaflows !== null) {
            $data['metaflows'] = $this->metaflows->toSwitchData();
        }

        return $data;
    }
}
