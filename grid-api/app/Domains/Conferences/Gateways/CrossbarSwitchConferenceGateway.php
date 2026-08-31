<?php

namespace App\Domains\Conferences\Gateways;

use App\Domains\Conferences\Contracts\SwitchConferenceGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use Generator;
use GridPbx\Switch\Domains\Conferences\ConferenceResourceClient;
use GridPbx\Switch\Domains\Conferences\Dto\ConferenceWriteData;

class CrossbarSwitchConferenceGateway implements SwitchConferenceGateway
{
    public function __construct(private readonly ConferenceResourceClient $conferences) {}

    public function all(SwitchAccount $account): Generator
    {
        foreach ($this->conferences->allDetails($account->switch_account_id) as $conference) {
            yield $conference->toArray();
        }
    }

    public function create(SwitchAccount $account, array $data): array
    {
        return $this->conferences->create($account->switch_account_id, $this->writeData($data))->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->conferences->update($account->switch_account_id, $resourceId, $this->writeData($data))->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->conferences->delete($account->switch_account_id, $resourceId);
    }

    /** @param array<string, mixed> $data */
    private function writeData(array $data): ConferenceWriteData
    {
        return new ConferenceWriteData(
            name: (string) $data['name'], ownerId: $data['switch_owner_reference'] ?? null,
            conferenceNumbers: $data['conference_numbers'], memberNumbers: $data['member_numbers'],
            moderatorNumbers: $data['moderator_numbers'], memberPins: $data['member_pins'],
            clearMemberPin: (bool) $data['clear_member_pin'], moderatorPins: $data['moderator_pins'],
            clearModeratorPin: (bool) $data['clear_moderator_pin'], memberJoinMuted: (bool) $data['member_join_muted'],
            memberJoinDeaf: (bool) $data['member_join_deaf'], memberPlayEntryPrompt: (bool) $data['member_play_entry_prompt'],
            moderatorJoinMuted: (bool) $data['moderator_join_muted'], moderatorJoinDeaf: (bool) $data['moderator_join_deaf'],
            maxParticipants: $data['max_participants'] ?? null, language: $data['language'] ?? null,
            profileName: $data['profile_name'] ?? null, callerControls: $data['caller_controls'] ?? null,
            moderatorControls: $data['moderator_controls'] ?? null, playName: (bool) $data['play_name'],
            playWelcome: (bool) $data['play_welcome'], requireModerator: (bool) $data['require_moderator'],
            waitForModerator: (bool) $data['wait_for_moderator'],
            maxMembersMediaId: $data['switch_max_members_media_reference'] ?? null,
            clearMaxMembersMedia: (bool) ($data['clear_switch_max_members_media'] ?? false),
            playEntryTone: $data['switch_play_entry_tone'] ?? null,
            playExitTone: $data['switch_play_exit_tone'] ?? null,
        );
    }
}
