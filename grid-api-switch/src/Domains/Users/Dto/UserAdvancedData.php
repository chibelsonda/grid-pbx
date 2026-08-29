<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto;

use GridPbx\Switch\Domains\Users\Dto\CallerId\UserCallerIdData;
use GridPbx\Switch\Domains\Users\Dto\CallForwarding\UserCallForwardData;
use GridPbx\Switch\Domains\Users\Dto\CallRecording\UserCallRecordingData;
use GridPbx\Switch\Domains\Users\Dto\CallRestrictions\UserCallRestrictionsData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserMediaData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserMusicOnHoldData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserPronouncedNameData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserRingtonesData;
use GridPbx\Switch\Domains\Users\Dto\Metaflows\UserMetaflowsData;
use GridPbx\Switch\Domains\Users\Dto\Profile\UserProfileData;
use GridPbx\Switch\Domains\Users\Dto\Routing\UserDialPlanData;
use GridPbx\Switch\Domains\Users\Dto\Routing\UserFormattersData;

final readonly class UserAdvancedData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public ?string $language = null,
        public ?string $presenceId = null,
        public ?bool $callWaiting = null,
        public ?bool $doNotDisturb = null,
        public ?bool $excludeFromContactList = null,
        public ?string $outboundPrivacy = null,
        public ?UserMetaflowsData $metaflows = null,
        public ?UserCallerIdData $callerId = null,
        public ?UserCallForwardData $callForward = null,
        public ?UserCallRestrictionsData $callRestrictions = null,
        public ?UserCallRecordingData $callRecording = null,
        public ?UserMediaData $media = null,
        public ?UserMusicOnHoldData $musicOnHold = null,
        public ?UserRingtonesData $ringtones = null,
        public ?UserDialPlanData $dialPlan = null,
        public ?UserFormattersData $formatters = null,
        public ?UserProfileData $profile = null,
        public ?UserPronouncedNameData $pronouncedName = null,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = array_merge($this->preservedOptions, array_filter([
            'language' => $this->language,
            'presence_id' => $this->presenceId,
        ], static fn (?string $value): bool => $value !== null));

        if ($this->callWaiting !== null) {
            $data['call_waiting'] = ['enabled' => $this->callWaiting];
        }

        if ($this->doNotDisturb !== null) {
            $data['do_not_disturb'] = ['enabled' => $this->doNotDisturb];
        }

        if ($this->excludeFromContactList !== null) {
            $data['contact_list'] = ['exclude' => $this->excludeFromContactList];
        }

        if ($this->outboundPrivacy !== null) {
            $data['caller_id_options'] = ['outbound_privacy' => $this->outboundPrivacy];
        }

        if ($this->metaflows !== null) {
            $data['metaflows'] = $this->metaflows->toSwitchData();
        }

        if ($this->callerId !== null) {
            $data['caller_id'] = $this->callerId->toSwitchData();
        }

        if ($this->callForward !== null) {
            $data['call_forward'] = $this->callForward->toSwitchData();
        }

        if ($this->callRestrictions !== null) {
            $data['call_restriction'] = $this->callRestrictions->toSwitchData();
        }

        if ($this->callRecording !== null) {
            $data['call_recording'] = $this->callRecording->toSwitchData();
        }

        if ($this->media !== null) {
            $data['media'] = $this->media->toSwitchData();
        }

        if ($this->musicOnHold !== null) {
            $data['music_on_hold'] = $this->musicOnHold->toSwitchData();
        }

        if ($this->ringtones !== null) {
            $data['ringtones'] = $this->ringtones->toSwitchData();
        }

        if ($this->dialPlan !== null) {
            $data['dial_plan'] = $this->dialPlan->toSwitchData();
        }

        if ($this->formatters !== null) {
            $data['formatters'] = $this->formatters->toSwitchData();
        }

        if ($this->profile !== null) {
            $data['profile'] = $this->profile->toSwitchData();
        }

        if ($this->pronouncedName !== null) {
            $data['pronounced_name'] = $this->pronouncedName->toSwitchData();
        }

        return $data;
    }
}
