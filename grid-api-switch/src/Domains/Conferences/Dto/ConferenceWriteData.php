<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Conferences\Dto;

use InvalidArgumentException;

final readonly class ConferenceWriteData
{
    /**
     * @param  list<string>  $conferenceNumbers
     * @param  list<string>  $memberNumbers
     * @param  list<string>  $moderatorNumbers
     * @param  list<string>  $memberPins
     * @param  list<string>  $moderatorPins
     */
    public function __construct(
        public string $name,
        public ?string $ownerId = null,
        public array $conferenceNumbers = [],
        public array $memberNumbers = [],
        public array $moderatorNumbers = [],
        public array $memberPins = [],
        public bool $clearMemberPin = false,
        public array $moderatorPins = [],
        public bool $clearModeratorPin = false,
        public bool $memberJoinMuted = true,
        public bool $memberJoinDeaf = false,
        public bool $memberPlayEntryPrompt = false,
        public bool $moderatorJoinMuted = false,
        public bool $moderatorJoinDeaf = false,
        public ?int $maxParticipants = null,
        public ?string $language = null,
        public ?string $profileName = null,
        public ?string $callerControls = null,
        public ?string $moderatorControls = null,
        public bool $playName = false,
        public bool $playWelcome = true,
        public bool $requireModerator = false,
        public bool $waitForModerator = false,
        public ?string $maxMembersMediaId = null,
        public bool $clearMaxMembersMedia = false,
        public bool|string|null $playEntryTone = null,
        public bool|string|null $playExitTone = null,
    ) {
        if (trim($this->name) === '' || mb_strlen($this->name) > 128) {
            throw new InvalidArgumentException('Switch conference name must contain between 1 and 128 characters.');
        }

        if ($this->ownerId !== null && strlen($this->ownerId) !== 32) {
            throw new InvalidArgumentException('Switch conference owner identifier must contain 32 characters.');
        }

        foreach ([$this->conferenceNumbers, $this->memberNumbers, $this->moderatorNumbers] as $numbers) {
            if (count($numbers) > 20) {
                throw new InvalidArgumentException('Switch conference access numbers must contain at most 20 values per role.');
            }

            foreach ($numbers as $number) {
                if (! is_string($number) || $number === '' || strlen($number) > 32 || ! ctype_digit($number)) {
                    throw new InvalidArgumentException('Switch conference access numbers must contain between 1 and 32 digits.');
                }
            }

            if (count($numbers) !== count(array_unique($numbers))) {
                throw new InvalidArgumentException('Switch conference access numbers must be unique per role.');
            }
        }

        foreach ([$this->memberPins, $this->moderatorPins] as $pins) {
            if (count($pins) > 20) {
                throw new InvalidArgumentException('Switch conference PINs must contain at most 20 values per role.');
            }

            foreach ($pins as $pin) {
                if (! is_string($pin) || $pin === '' || strlen($pin) > 32 || ! ctype_digit($pin)) {
                    throw new InvalidArgumentException('Switch conference PINs must contain between 1 and 32 digits.');
                }
            }

            if (count($pins) !== count(array_unique($pins))) {
                throw new InvalidArgumentException('Switch conference PINs must be unique per role.');
            }
        }

        if ($this->memberPins !== [] && $this->clearMemberPin) {
            throw new InvalidArgumentException('Member PINs cannot be set and cleared in the same request.');
        }

        if ($this->moderatorPins !== [] && $this->clearModeratorPin) {
            throw new InvalidArgumentException('Moderator PINs cannot be set and cleared in the same request.');
        }

        if ($this->maxParticipants !== null && ($this->maxParticipants < 1 || $this->maxParticipants > 10000)) {
            throw new InvalidArgumentException('Switch conference maximum participants must be between 1 and 10000.');
        }

        foreach ([
            [$this->language, 16],
            [$this->profileName, 128],
            [$this->callerControls, 128],
            [$this->moderatorControls, 128],
        ] as [$value, $maximum]) {
            if ($value !== null && mb_strlen($value) > $maximum) {
                throw new InvalidArgumentException('Switch conference text setting exceeds its supported length.');
            }
        }

        if ($this->maxMembersMediaId !== null && mb_strlen($this->maxMembersMediaId) > 2048) {
            throw new InvalidArgumentException('Switch conference media reference is too long.');
        }

        foreach ([$this->playEntryTone, $this->playExitTone] as $tone) {
            if (is_string($tone) && (trim($tone) === '' || mb_strlen($tone) > 2048)) {
                throw new InvalidArgumentException('Switch conference custom tone references must contain between 1 and 2048 characters.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchPatchData(): array
    {
        return array_merge($this->toSwitchData(), [
            'owner_id' => $this->ownerId,
            'max_participants' => $this->maxParticipants,
            'language' => $this->language,
            'profile_name' => $this->profileName,
            'caller_controls' => $this->callerControls,
            'moderator_controls' => $this->moderatorControls,
        ]);
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $member = [
            'numbers' => array_values($this->memberNumbers),
            'join_muted' => $this->memberJoinMuted,
            'join_deaf' => $this->memberJoinDeaf,
            'play_entry_prompt' => $this->memberPlayEntryPrompt,
        ];
        $moderator = [
            'numbers' => array_values($this->moderatorNumbers),
            'join_muted' => $this->moderatorJoinMuted,
            'join_deaf' => $this->moderatorJoinDeaf,
        ];

        if ($this->memberPins !== [] || $this->clearMemberPin) {
            $member['pins'] = array_values($this->memberPins);
        }

        if ($this->moderatorPins !== [] || $this->clearModeratorPin) {
            $moderator['pins'] = array_values($this->moderatorPins);
        }

        $data = array_filter([
            'name' => $this->name,
            'owner_id' => $this->ownerId,
            'conference_numbers' => array_values($this->conferenceNumbers),
            'member' => $member,
            'moderator' => $moderator,
            'max_participants' => $this->maxParticipants,
            'language' => $this->language,
            'profile_name' => $this->profileName,
            'caller_controls' => $this->callerControls,
            'moderator_controls' => $this->moderatorControls,
            'play_name' => $this->playName,
            'play_welcome' => $this->playWelcome,
            'require_moderator' => $this->requireModerator,
            'wait_for_moderator' => $this->waitForModerator,
            'max_members_media' => $this->maxMembersMediaId,
            'play_entry_tone' => $this->playEntryTone,
            'play_exit_tone' => $this->playExitTone,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($this->clearMaxMembersMedia) {
            $data['max_members_media'] = null;
        }

        return $data;
    }
}
