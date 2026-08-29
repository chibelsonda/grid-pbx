<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Conferences\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class ConferenceSnapshot extends EntitySnapshot
{
    public string $name;
    public ?string $ownerId;
    /** @var list<string> */
    public array $conferenceNumbers;
    /** @var list<string> */
    public array $memberNumbers;
    /** @var list<string> */
    public array $moderatorNumbers;
    public bool $memberPinConfigured;
    public bool $moderatorPinConfigured;
    public bool $memberJoinMuted;
    public bool $memberJoinDeaf;
    public bool $memberPlayEntryPrompt;
    public bool $moderatorJoinMuted;
    public bool $moderatorJoinDeaf;
    public ?int $maxParticipants;
    public ?string $language;
    public ?string $profileName;
    public ?string $callerControls;
    public ?string $moderatorControls;
    public bool $playName;
    public bool $playWelcome;
    public bool $requireModerator;
    public bool $waitForModerator;
    public int $activeMembers;
    public int $activeModerators;
    public int $durationSeconds;
    public bool $isLocked;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $name = $data['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            throw new InvalidSwitchPayloadException('Switch conference response is missing its name.');
        }

        $member = is_array($data['member'] ?? null) ? $data['member'] : [];
        $moderator = is_array($data['moderator'] ?? null) ? $data['moderator'] : [];
        $realtime = is_array($data['_read_only'] ?? null) ? $data['_read_only'] : [];

        $this->name = $name;
        $this->ownerId = $this->nullableString($data['owner_id'] ?? null);
        $this->conferenceNumbers = $this->stringList($data['conference_numbers'] ?? null);
        $this->memberNumbers = $this->stringList($member['numbers'] ?? null);
        $this->moderatorNumbers = $this->stringList($moderator['numbers'] ?? null);
        $this->memberPinConfigured = $this->stringList($member['pins'] ?? null) !== [];
        $this->moderatorPinConfigured = $this->stringList($moderator['pins'] ?? null) !== [];
        $this->memberJoinMuted = (bool) ($member['join_muted'] ?? true);
        $this->memberJoinDeaf = (bool) ($member['join_deaf'] ?? false);
        $this->memberPlayEntryPrompt = (bool) ($member['play_entry_prompt'] ?? false);
        $this->moderatorJoinMuted = (bool) ($moderator['join_muted'] ?? false);
        $this->moderatorJoinDeaf = (bool) ($moderator['join_deaf'] ?? false);
        $this->maxParticipants = is_int($data['max_participants'] ?? null) ? max(1, $data['max_participants']) : null;
        $this->language = $this->nullableString($data['language'] ?? null);
        $this->profileName = $this->nullableString($data['profile_name'] ?? null);
        $this->callerControls = $this->nullableString($data['caller_controls'] ?? null);
        $this->moderatorControls = $this->nullableString($data['moderator_controls'] ?? null);
        $this->playName = (bool) ($data['play_name'] ?? false);
        $this->playWelcome = (bool) ($data['play_welcome'] ?? true);
        $this->requireModerator = (bool) ($data['require_moderator'] ?? false);
        $this->waitForModerator = (bool) ($data['wait_for_moderator'] ?? false);
        $this->activeMembers = max(0, (int) ($realtime['members'] ?? 0));
        $this->activeModerators = max(0, (int) ($realtime['moderators'] ?? 0));
        $this->durationSeconds = max(0, (int) ($realtime['duration'] ?? 0));
        $this->isLocked = (bool) ($realtime['is_locked'] ?? false);
    }
}
