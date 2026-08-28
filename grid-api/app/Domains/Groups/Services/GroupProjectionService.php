<?php

namespace App\Domains\Groups\Services;

use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use UnexpectedValueException;

class GroupProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redactSensitiveData) {}

    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchGroup
    {
        $resourceId = $this->stringValue($snapshot['id'] ?? null);
        $name = $this->stringValue($snapshot['name'] ?? null);

        if ($resourceId === null || $name === null) {
            throw new UnexpectedValueException('Switch group response is missing required metadata.');
        }

        $mediaResourceId = is_array($snapshot['music_on_hold'] ?? null)
            ? $this->stringValue($snapshot['music_on_hold']['media_id'] ?? null)
            : null;
        $group = SwitchGroup::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(), 'switch_resource_id' => $resourceId,
        ]);
        $group->fill([
            'name' => $name,
            'music_on_hold_media_id' => $mediaResourceId === null ? null : $account->media()->where('switch_resource_id', $mediaResourceId)->value('media_id'),
            'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $group->exists ? $group->projection_version + 1 : 1,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $group->deleted_at = null;
        $group->save();
        $seen = [];

        foreach (is_array($snapshot['endpoints'] ?? null) ? $snapshot['endpoints'] : [] as $memberResourceId => $metadata) {
            if (! is_string($memberResourceId) || ! is_array($metadata)) {
                continue;
            }

            $type = $metadata['type'] ?? null;

            if (! in_array($type, ['user', 'device', 'group'], true)) {
                continue;
            }

            $member = $group->members()->updateOrCreate([
                'member_type' => $type, 'switch_member_resource_id' => $memberResourceId,
            ], [
                'switch_extension_id' => $type === 'user' ? $account->extensions()->where('switch_resource_id', $memberResourceId)->value('extension_id') : null,
                'switch_device_id' => $type === 'device' ? $account->devices()->where('switch_resource_id', $memberResourceId)->value('device_id') : null,
                'nested_switch_group_id' => $type === 'group' ? $account->groups()->where('switch_resource_id', $memberResourceId)->value('group_id') : null,
                'weight' => max(1, min(100, (int) ($metadata['weight'] ?? 1))),
            ]);
            $seen[] = $member->getKey();
        }

        $group->members()->when($seen !== [], fn ($query) => $query->whereNotIn('group_member_id', $seen))->delete();

        if ($seen === []) {
            $group->members()->delete();
        }

        return $group->load(['members.extension', 'members.device', 'members.nestedGroup', 'musicOnHoldMedia']);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function reconcileNestedGroups(SwitchAccount $account): void
    {
        $account->groups()->with('members')->get()->each(function (SwitchGroup $group) use ($account): void {
            foreach ($group->members->where('member_type', 'group') as $member) {
                $member->update([
                    'nested_switch_group_id' => $account->groups()
                        ->where('switch_resource_id', $member->switch_member_resource_id)
                        ->value('group_id'),
                ]);
            }
        });
    }
}
