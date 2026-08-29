<?php

namespace App\Domains\Groups\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Groups\Contracts\SwitchGroupGateway;
use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class GroupMutationService
{
    public function __construct(
        private readonly SwitchGroupGateway $gateway,
        private readonly GroupProjectionService $projection,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(SwitchAccount $account, User $actor, array $data, ?string $ipAddress = null): SwitchGroup
    {
        $resolved = $this->resolve($account, null, $data);
        $resourceId = null;

        try {
            $snapshot = $this->gateway->create($account, $resolved);
            $resourceId = is_string($snapshot['id'] ?? null) ? $snapshot['id'] : null;

            return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchGroup {
                $group = $this->projection->project($account, $snapshot);
                $this->audit->record($actor, $account, 'group.created', 'succeeded', $group->switch_resource_id, [], $ipAddress, 'group');

                return $group;
            });
        } catch (Throwable $exception) {
            if ($resourceId !== null) {
                try {
                    $this->gateway->delete($account, $resourceId);
                } catch (Throwable) {
                }
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(SwitchAccount $account, SwitchGroup $group, User $actor, array $data, ?string $ipAddress = null): SwitchGroup
    {
        $snapshot = $this->gateway->update($account, $group->switch_resource_id, $this->resolve($account, $group, $data));

        return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchGroup {
            $updated = $this->projection->project($account, $snapshot);
            $this->audit->record($actor, $account, 'group.updated', 'succeeded', $updated->switch_resource_id, [], $ipAddress, 'group');

            return $updated;
        });
    }

    public function delete(SwitchAccount $account, SwitchGroup $group, User $actor, ?string $ipAddress = null): void
    {
        if ($account->groups()->whereHas('members', fn ($query) => $query->where('nested_switch_group_id', $group->getKey()))->exists()) {
            throw ValidationException::withMessages(['group' => ['Remove this group from its parent groups before deleting it.']]);
        }

        foreach ($account->callflows()->get() as $callflow) {
            if ($this->containsGroup($callflow->switch_json['flow'] ?? null, $group->switch_resource_id)) {
                throw ValidationException::withMessages(['group' => ['Remove this group from call routing before deleting it.']]);
            }
        }

        $this->gateway->delete($account, $group->switch_resource_id);
        DB::transaction(function () use ($account, $actor, $group, $ipAddress): void {
            $group->delete();
            $this->audit->record($actor, $account, 'group.deleted', 'succeeded', $group->switch_resource_id, [], $ipAddress, 'group');
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolve(SwitchAccount $account, ?SwitchGroup $group, array $data): array
    {
        $resolvedMembers = [];

        foreach ($data['members'] as $member) {
            $target = match ($member['type']) {
                'user' => $account->extensions()->where('id', $member['id'])->first(),
                'device' => $account->devices()->where('id', $member['id'])->first(),
                'group' => $account->groups()->where('id', $member['id'])->first(),
            };

            if ($target === null || ($group !== null && $member['type'] === 'group' && $target->is($group))) {
                throw ValidationException::withMessages(['members' => ['One or more selected group members are unavailable.']]);
            }

            if ($group !== null && $member['type'] === 'group' && $this->containsNestedGroup($target, $group->getKey())) {
                throw ValidationException::withMessages(['members' => ['Nested groups cannot create a membership cycle.']]);
            }

            $resolvedMembers[] = ['type' => $member['type'], 'switch_resource_id' => $target->switch_resource_id, 'weight' => $member['weight']];
        }

        $media = empty($data['music_on_hold_media_id']) ? null : $account->media()->where('id', $data['music_on_hold_media_id'])->first();

        if (! empty($data['music_on_hold_media_id']) && $media === null) {
            throw ValidationException::withMessages(['music_on_hold_media_id' => ['The selected media is unavailable for this account.']]);
        }

        return [
            ...$data,
            'resolved_members' => $resolvedMembers,
            'switch_music_on_hold_media_id' => $media?->switch_resource_id,
            'switch_flags' => $group === null ? [] : $this->stringList($group->switch_json['flags'] ?? null),
        ];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(
            is_array($value) ? $value : [],
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    private function containsNestedGroup(SwitchGroup $candidate, string $targetKey, array $seen = []): bool
    {
        if (isset($seen[$candidate->getKey()])) {
            return false;
        }
        $seen[$candidate->getKey()] = true;

        foreach ($candidate->members()->whereNotNull('nested_switch_group_id')->with('nestedGroup')->get() as $member) {
            if ($member->nested_switch_group_id === $targetKey || ($member->nestedGroup !== null && $this->containsNestedGroup($member->nestedGroup, $targetKey, $seen))) {
                return true;
            }
        }

        return false;
    }

    private function containsGroup(mixed $node, string $resourceId): bool
    {
        if (! is_array($node)) {
            return false;
        }
        $module = $node['module'] ?? null;
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        if (($module === 'group' && ($data['id'] ?? null) === $resourceId) || ($module === 'ring_group' && $this->ringGroupContains($data['endpoints'] ?? [], $resourceId))) {
            return true;
        }

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if ($this->containsGroup($child, $resourceId)) {
                return true;
            }
        }

        return false;
    }

    private function ringGroupContains(mixed $endpoints, string $resourceId): bool
    {
        if (! is_array($endpoints)) {
            return false;
        }
        foreach ($endpoints as $endpoint) {
            if (is_array($endpoint) && ($endpoint['endpoint_type'] ?? null) === 'group' && ($endpoint['id'] ?? null) === $resourceId) {
                return true;
            }
        }

        return false;
    }
}
