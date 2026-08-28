<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Conferences;

use InvalidArgumentException;

final readonly class ConferenceWriteData
{
    /**
     * @param list<string> $conferenceNumbers
     * @param list<string> $memberNumbers
     * @param list<string> $moderatorNumbers
     */
    public function __construct(
        public string $name,
        public ?string $ownerId = null,
        public array $conferenceNumbers = [],
        public array $memberNumbers = [],
        public array $moderatorNumbers = [],
        public ?string $memberPin = null,
        public bool $clearMemberPin = false,
        public ?string $moderatorPin = null,
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
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch conference name is required.');
        }

        foreach ([...$this->conferenceNumbers, ...$this->memberNumbers, ...$this->moderatorNumbers] as $number) {
            if ($number === '' || ! ctype_digit($number)) {
                throw new InvalidArgumentException('Switch conference access numbers must contain digits only.');
            }
        }

        foreach ([$this->memberPin, $this->moderatorPin] as $pin) {
            if ($pin !== null && ($pin === '' || ! ctype_digit($pin))) {
                throw new InvalidArgumentException('Switch conference PINs must contain digits only.');
            }
        }

        if ($this->memberPin !== null && $this->clearMemberPin) {
            throw new InvalidArgumentException('A member PIN cannot be set and cleared in the same request.');
        }

        if ($this->moderatorPin !== null && $this->clearModeratorPin) {
            throw new InvalidArgumentException('A moderator PIN cannot be set and cleared in the same request.');
        }

        if ($this->maxParticipants !== null && $this->maxParticipants < 1) {
            throw new InvalidArgumentException('Switch conference maximum participants must be greater than zero.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $member = [
            'numbers' => array_values(array_unique($this->memberNumbers)),
            'join_muted' => $this->memberJoinMuted,
            'join_deaf' => $this->memberJoinDeaf,
            'play_entry_prompt' => $this->memberPlayEntryPrompt,
        ];
        $moderator = [
            'numbers' => array_values(array_unique($this->moderatorNumbers)),
            'join_muted' => $this->moderatorJoinMuted,
            'join_deaf' => $this->moderatorJoinDeaf,
        ];

        if ($this->memberPin !== null || $this->clearMemberPin) {
            $member['pins'] = $this->memberPin === null ? [] : [$this->memberPin];
        }

        if ($this->moderatorPin !== null || $this->clearModeratorPin) {
            $moderator['pins'] = $this->moderatorPin === null ? [] : [$this->moderatorPin];
        }

        return array_filter([
            'name' => $this->name,
            'owner_id' => $this->ownerId,
            'conference_numbers' => array_values(array_unique($this->conferenceNumbers)),
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
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
