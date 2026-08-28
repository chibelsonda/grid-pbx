<?php

namespace App\Domains\Conferences\Services;

use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use UnexpectedValueException;

class ConferenceProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redactSensitiveData) {}

    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchConference
    {
        $resourceId = $this->stringValue($snapshot['id'] ?? null);
        $name = $this->stringValue($snapshot['name'] ?? null);
        if ($resourceId === null || $name === null) {
            throw new UnexpectedValueException('Switch conference response is missing required metadata.');
        }

        $member = is_array($snapshot['member'] ?? null) ? $snapshot['member'] : [];
        $moderator = is_array($snapshot['moderator'] ?? null) ? $snapshot['moderator'] : [];
        $realtime = is_array($snapshot['_read_only'] ?? null) ? $snapshot['_read_only'] : [];
        $ownerReference = $this->stringValue($snapshot['owner_id'] ?? null);
        $conference = SwitchConference::withTrashed()->firstOrNew(['switch_account_id' => $account->getKey(), 'switch_resource_id' => $resourceId]);
        $conference->fill([
            'owner_switch_resource_id' => $ownerReference,
            'owner_extension_id' => $ownerReference === null ? null : $account->extensions()->where('switch_resource_id', $ownerReference)->value('extension_id'),
            'name' => $name, 'member_pin_configured' => $this->strings($member['pins'] ?? null) !== [],
            'moderator_pin_configured' => $this->strings($moderator['pins'] ?? null) !== [],
            'member_join_muted' => (bool) ($member['join_muted'] ?? true), 'member_join_deaf' => (bool) ($member['join_deaf'] ?? false),
            'member_play_entry_prompt' => (bool) ($member['play_entry_prompt'] ?? false),
            'moderator_join_muted' => (bool) ($moderator['join_muted'] ?? false), 'moderator_join_deaf' => (bool) ($moderator['join_deaf'] ?? false),
            'max_participants' => is_numeric($snapshot['max_participants'] ?? null) ? max(1, (int) $snapshot['max_participants']) : null,
            'language' => $this->stringValue($snapshot['language'] ?? null), 'profile_name' => $this->stringValue($snapshot['profile_name'] ?? null),
            'caller_controls' => $this->stringValue($snapshot['caller_controls'] ?? null), 'moderator_controls' => $this->stringValue($snapshot['moderator_controls'] ?? null),
            'play_name' => (bool) ($snapshot['play_name'] ?? false), 'play_welcome' => (bool) ($snapshot['play_welcome'] ?? true),
            'require_moderator' => (bool) ($snapshot['require_moderator'] ?? false), 'wait_for_moderator' => (bool) ($snapshot['wait_for_moderator'] ?? false),
            'active_members' => max(0, (int) ($realtime['members'] ?? 0)), 'active_moderators' => max(0, (int) ($realtime['moderators'] ?? 0)),
            'duration_seconds' => max(0, (int) ($realtime['duration'] ?? 0)), 'is_locked' => (bool) ($realtime['is_locked'] ?? false),
            'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $conference->exists ? $conference->projection_version + 1 : 1,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $conference->deleted_at = null;
        $conference->save();
        $conference->numbers()->delete();
        foreach (['conference' => $snapshot['conference_numbers'] ?? null, 'member' => $member['numbers'] ?? null, 'moderator' => $moderator['numbers'] ?? null] as $role => $numbers) {
            foreach ($this->strings($numbers) as $number) {
                $conference->numbers()->create(['role' => $role, 'number' => $number]);
            }
        }

        return $conference->load(['owner', 'numbers']);
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_unique(array_filter($value, fn ($item) => is_string($item) && $item !== ''))) : [];
    }

    private function stringValue(mixed $value): ?string { return is_string($value) && $value !== '' ? $value : null; }
}
