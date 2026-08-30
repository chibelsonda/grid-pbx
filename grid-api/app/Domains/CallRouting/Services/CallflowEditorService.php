<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Validation\ValidationException;

class CallflowEditorService
{
    /** @var list<string> */
    private const MENU_BRANCH_KEYS = [
        'timeout',
        '0',
        '1',
        '2',
        '3',
        '4',
        '5',
        '6',
        '7',
        '8',
        '9',
        '*',
    ];

    /** @var list<string> */
    private const GUIDED_ROOT_MODULES = [
        'user',
        'device',
        'voicemail',
        'callflow',
        'play',
        'directory',
        'group',
        'acdc_member',
        'menu',
        'conference',
        'faxbox',
        'temporal_route',
    ];

    /** @return array<string, mixed> */
    public function editor(SwitchAccount $account, ?SwitchCallflow $callflow = null): array
    {
        $temporalRuleSets = $account->temporalRuleSets()
            ->with(['rules.rule'])
            ->withCount('rules')
            ->orderBy('name')
            ->get();
        $temporalRules = $account->temporalRules()->orderBy('name')->get();

        return [
            'mode' => $callflow === null ? 'create' : 'update',
            'editable' => $callflow === null || $this->blockedReason($callflow) === null,
            'blocked_reason' => $callflow === null ? null : $this->blockedReason($callflow),
            'fallback' => $this->fallbackEditor($callflow),
            'menu_branches' => $this->menuBranches($callflow),
            'temporal_match' => $this->temporalMatch($callflow),
            'direct_temporal_routes' => $this->directTemporalRoutes($account, $callflow),
            'temporal_rule_sets' => $temporalRuleSets->mapWithKeys(fn ($set): array => [
                $set->id => $set->rules->map(fn ($membership): array => [
                    'id' => $membership->rule?->id,
                    'label' => $membership->rule?->name ?? 'Unresolved schedule rule',
                    'position' => $membership->position,
                    'resolved' => $membership->rule !== null,
                ])->values()->all(),
            ])->all(),
            'temporal_rules' => $temporalRules->map(fn ($rule): array => [
                'id' => $rule->id,
                'label' => $rule->name,
                'detail' => $rule->cycle === null
                    ? 'Temporal Rule'
                    : ucfirst(str_replace('_', ' ', $rule->cycle)).' recurrence',
            ])->values()->all(),
            'caller_id_lists' => $account->callerIdLists()
                ->withCount('entries')
                ->orderBy('name')
                ->get()
                ->map(fn ($list): array => [
                    'id' => $list->id,
                    'label' => $list->name,
                    'detail' => $list->entries_count.' entries',
                ])
                ->values()
                ->all(),
            'destination_types' => [
                ['value' => 'extension', 'label' => 'Extension'],
                ['value' => 'device', 'label' => 'Device'],
                ['value' => 'voicemail', 'label' => 'Voicemail'],
                ['value' => 'callflow', 'label' => 'Another call route'],
                ['value' => 'media', 'label' => 'Media'],
                ['value' => 'directory', 'label' => 'Directory'],
                ['value' => 'group', 'label' => 'Group / Ring Group'],
                ['value' => 'queue', 'label' => 'Call Queue'],
                ['value' => 'menu', 'label' => 'Menu / IVR'],
                ['value' => 'conference', 'label' => 'Conference'],
                ['value' => 'fax_box', 'label' => 'Fax Box'],
                ['value' => 'temporal_rule_set', 'label' => 'Business Hours / Schedule'],
                ['value' => 'temporal_rules', 'label' => 'Direct Temporal Rules'],
            ],
            'destinations' => [
                'extension' => $account->extensions()->orderBy('display_name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->display_name ?? $item->extension ?? 'Unnamed extension',
                    'detail' => $item->extension,
                ])->values()->all(),
                'device' => $account->devices()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name ?? 'Unnamed device',
                    'detail' => $item->device_type,
                ])->values()->all(),
                'voicemail' => $account->voicemailBoxes()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name ?? $item->mailbox ?? 'Unnamed mailbox',
                    'detail' => $item->mailbox,
                ])->values()->all(),
                'callflow' => $account->callflows()
                    ->when($callflow !== null, fn ($query) => $query->whereKeyNot($callflow->getKey()))
                    ->orderBy('name')->get()->map(fn ($item): array => [
                        'id' => $item->id,
                        'label' => $item->name ?? ($item->numbers[0] ?? 'Unnamed route'),
                        'detail' => $item->root_module,
                        'supports_ring_group_toggle' => $item->canBeRingGroupToggleTarget(),
                    ])->values()->all(),
                'media' => $account->media()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name ?? 'Voicemail greeting',
                    'detail' => $item->content_type,
                    'supports_ringback' => is_string($item->switch_resource_id)
                        && $item->switch_resource_id !== ''
                        && $item->streamable === true
                        && is_string($item->content_type)
                        && str_starts_with($item->content_type, 'audio/'),
                ])->values()->all(),
                'directory' => $account->directories()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name,
                    'detail' => 'Dial-by-name directory',
                ])->values()->all(),
                'group' => $account->groups()->withCount('members')->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name,
                    'detail' => $item->members_count.' members',
                ])->values()->all(),
                'queue' => $account->queues()->withCount('agents')->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name,
                    'detail' => $item->agents_count.' agents',
                ])->values()->all(),
                'menu' => $account->menus()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name,
                    'detail' => 'Interactive voice menu',
                ])->values()->all(),
                'conference' => $account->conferences()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name,
                    'detail' => $item->active_members.' active participants',
                ])->values()->all(),
                'fax_box' => $account->faxBoxes()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id, 'label' => $item->name, 'detail' => $item->smtp_email_address,
                ])->values()->all(),
                'temporal_rule_set' => $temporalRuleSets->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name,
                    'detail' => $item->rules_count.' schedule rules',
                ])->values()->all(),
                'temporal_rules' => [],
            ],
            'phone_numbers' => $account->phoneNumbers()
                ->with('assignedCallflow:callflow_id,id,name')
                ->orderBy('number')
                ->get()
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'number' => $item->number,
                    'state' => $item->state,
                    'selected' => $callflow !== null && $item->assigned_callflow_id === $callflow->getKey(),
                    'available' => $item->assigned_callflow_id === null
                        || ($callflow !== null && $item->assigned_callflow_id === $callflow->getKey()),
                    'assigned_callflow' => $item->assignedCallflow === null ? null : [
                        'id' => $item->assignedCallflow->id,
                        'name' => $item->assignedCallflow->name,
                    ],
                ])->values()->all(),
        ];
    }

    public function assertEditable(SwitchCallflow $callflow): void
    {
        $reason = $this->blockedReason($callflow);

        if ($reason !== null) {
            throw ValidationException::withMessages(['callflow' => [$reason]]);
        }
    }

    public function assertFallbackEditable(SwitchCallflow $callflow): void
    {
        $fallback = $this->fallbackEditor($callflow);

        if (! $fallback['editable']) {
            throw ValidationException::withMessages([
                'fallback_destination_id' => [$fallback['blocked_reason']],
            ]);
        }
    }

    /** @param list<string> $keys */
    public function assertMenuBranchesEditable(SwitchCallflow $callflow, array $keys): void
    {
        $editor = $this->menuBranches($callflow);

        if (! $editor['editable']) {
            throw ValidationException::withMessages([
                'menu_branches' => [$editor['blocked_reason']],
            ]);
        }

        $branches = collect($editor['branches'])->keyBy('key');

        foreach ($keys as $key) {
            $branch = $branches->get($key);

            if (! is_array($branch) || ! $branch['editable']) {
                throw ValidationException::withMessages([
                    'menu_branches' => [sprintf(
                        'Menu key %s contains an unsafe branch and is preserved unchanged.',
                        $key,
                    )],
                ]);
            }
        }
    }

    public function assertTemporalMatchEditable(SwitchCallflow $callflow): void
    {
        $editor = $this->temporalMatch($callflow);

        if (! $editor['editable']) {
            throw ValidationException::withMessages([
                'temporal_match_destination_id' => [$editor['blocked_reason']],
            ]);
        }
    }

    public function assertDirectTemporalRoutesEditable(
        SwitchAccount $account,
        SwitchCallflow $callflow,
    ): void {
        $blocked = collect($this->directTemporalRoutes($account, $callflow))
            ->first(fn (array $route): bool => ! $route['editable']);

        if (is_array($blocked)) {
            throw ValidationException::withMessages([
                'temporal_rule_routes' => [$blocked['blocked_reason']],
            ]);
        }
    }

    private function blockedReason(SwitchCallflow $callflow): ?string
    {
        if ($callflow->is_feature_code) {
            return 'Feature-code routes are read-only in the guided editor.';
        }

        if ($callflow->flow_structure === null) {
            return 'This route has no root flow node to edit.';
        }

        $module = $callflow->flow_structure['module'] ?? null;

        if (! is_string($module) || ! in_array($module, self::GUIDED_ROOT_MODULES, true)) {
            return 'This route uses a root module that is not yet supported by the guided editor. Its Switch configuration is preserved unchanged.';
        }

        if (($callflow->flow_structure['reference_status'] ?? null) !== 'resolved') {
            return 'This route target is not available in the current projection. Synchronize the related resource before editing it.';
        }

        return null;
    }

    /** @return array{editable: bool, blocked_reason: ?string, target: ?array{type: string, id: string, label: string}} */
    private function fallbackEditor(?SwitchCallflow $callflow): array
    {
        if ($callflow === null) {
            return ['editable' => true, 'blocked_reason' => null, 'target' => null];
        }

        $fallback = $callflow->flow_structure['children']['_'] ?? null;

        if (! is_array($fallback)) {
            return ['editable' => true, 'blocked_reason' => null, 'target' => null];
        }

        if (is_array($fallback['children'] ?? null) && $fallback['children'] !== []) {
            return [
                'editable' => false,
                'blocked_reason' => 'The existing fallback contains nested branches and is read-only until recursive editing is enabled.',
                'target' => null,
            ];
        }

        $module = $fallback['module'] ?? null;
        $target = $fallback['target'] ?? null;

        if (! is_string($module)
            || ! in_array($module, self::GUIDED_ROOT_MODULES, true)
            || ($fallback['reference_status'] ?? null) !== 'resolved'
            || ! is_array($target)
            || ! is_string($target['type'] ?? null)
            || ! is_string($target['id'] ?? null)
            || ! is_string($target['label'] ?? null)) {
            return [
                'editable' => false,
                'blocked_reason' => 'The existing fallback uses an unsupported or unresolved target and is preserved unchanged.',
                'target' => null,
            ];
        }

        return [
            'editable' => true,
            'blocked_reason' => null,
            'target' => [
                'type' => $target['type'],
                'id' => $target['id'],
                'label' => $target['label'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function menuBranches(?SwitchCallflow $callflow): array
    {
        $children = $callflow === null
            ? []
            : (is_array($callflow->flow_structure['children'] ?? null)
                ? $callflow->flow_structure['children']
                : []);
        $hasMenuKeys = collect(self::MENU_BRANCH_KEYS)->contains(
            fn (string $key): bool => array_key_exists($key, $children),
        );
        $editable = $callflow === null
            || ($callflow->flow_structure['module'] ?? null) === 'menu'
            || ! $hasMenuKeys;
        $blockedReason = $editable
            ? null
            : 'This non-menu route already contains menu-shaped branches. They are preserved until the root is edited through the visual tree editor.';
        $labels = ['timeout' => 'Timeout', '*' => 'Star'];
        $branches = [];

        foreach (self::MENU_BRANCH_KEYS as $key) {
            $branch = $children[$key] ?? null;
            $state = $editable
                ? $this->leafBranchEditor($branch)
                : ['editable' => false, 'blocked_reason' => $blockedReason, 'target' => null];
            $branches[] = [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                ...$state,
            ];
        }

        return [
            'editable' => $editable,
            'blocked_reason' => $blockedReason,
            'branches' => $branches,
            'legacy_hash_present' => array_key_exists('#', $children),
            'unknown_branch_keys' => collect(array_keys($children))
                ->filter(fn (mixed $key): bool => is_string($key)
                    && $key !== '_'
                    && $key !== '#'
                    && ! in_array($key, self::MENU_BRANCH_KEYS, true))
                ->values()
                ->map(fn (mixed $_key, int $index): string => 'preserved_'.($index + 1))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function temporalMatch(?SwitchCallflow $callflow): array
    {
        if ($callflow === null) {
            return [
                'editable' => true,
                'blocked_reason' => null,
                'target' => null,
                'preserved_branch_count' => 0,
            ];
        }

        $children = is_array($callflow->flow_structure['children'] ?? null)
            ? $callflow->flow_structure['children']
            : [];
        $branch = $children['rule_set'] ?? null;
        $isTemporalRoot = ($callflow->flow_structure['module'] ?? null) === 'temporal_route';
        $editable = $isTemporalRoot || $branch === null;
        $blockedReason = $editable
            ? null
            : 'This non-temporal route already contains a rule-set branch. It is preserved until the root is edited through the visual tree editor.';
        $state = $editable
            ? $this->leafBranchEditor($branch)
            : ['editable' => false, 'blocked_reason' => $blockedReason, 'target' => null];

        return [
            ...$state,
            'preserved_branch_count' => collect(array_keys($children))
                ->filter(fn (mixed $key): bool => (string) $key !== '_' && (string) $key !== 'rule_set')
                ->count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function directTemporalRoutes(
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
    ): array {
        if ($callflow === null || ($callflow->flow_structure['module'] ?? null) !== 'temporal_route') {
            return [];
        }

        $rules = collect(is_array($callflow->flow_structure['temporal_rules'] ?? null)
            ? $callflow->flow_structure['temporal_rules']
            : []);
        $projectedRules = $account->temporalRules()
            ->whereIn('id', $rules->pluck('id')->filter()->all())
            ->get()
            ->keyBy('id');
        $children = is_array($callflow->flow_structure['children'] ?? null)
            ? $callflow->flow_structure['children']
            : [];

        return $rules->map(function (array $rule) use ($projectedRules, $children): array {
            $id = is_string($rule['id'] ?? null) ? $rule['id'] : null;
            $projected = $id === null ? null : $projectedRules->get($id);
            $state = $projected === null
                ? [
                    'editable' => false,
                    'blocked_reason' => 'Synchronize this Temporal Rule before editing its route.',
                    'target' => null,
                ]
                : $this->leafBranchEditor($children[$projected->switch_resource_id] ?? null);

            return [
                'rule_id' => $id,
                'label' => is_string($rule['label'] ?? null)
                    ? $rule['label']
                    : 'Unresolved Temporal Rule',
                'position' => is_int($rule['position'] ?? null) ? $rule['position'] : 0,
                'resolved' => (bool) ($rule['resolved'] ?? false),
                ...$state,
            ];
        })->values()->all();
    }

    /** @return array{editable: bool, blocked_reason: ?string, target: ?array{type: string, id: string, label: string}} */
    private function leafBranchEditor(mixed $branch): array
    {
        if ($branch === null) {
            return ['editable' => true, 'blocked_reason' => null, 'target' => null];
        }

        if (! is_array($branch)) {
            return [
                'editable' => false,
                'blocked_reason' => 'This branch is malformed and is preserved unchanged.',
                'target' => null,
            ];
        }

        if (is_array($branch['children'] ?? null) && $branch['children'] !== []) {
            return [
                'editable' => false,
                'blocked_reason' => 'This branch contains nested actions and is preserved unchanged.',
                'target' => null,
            ];
        }

        $module = $branch['module'] ?? null;
        $target = $branch['target'] ?? null;

        if (! is_string($module)
            || ! in_array($module, self::GUIDED_ROOT_MODULES, true)
            || ($branch['reference_status'] ?? null) !== 'resolved'
            || ! is_array($target)
            || ! is_string($target['type'] ?? null)
            || ! is_string($target['id'] ?? null)
            || ! is_string($target['label'] ?? null)) {
            return [
                'editable' => false,
                'blocked_reason' => 'This branch uses an unsupported or unresolved target and is preserved unchanged.',
                'target' => null,
            ];
        }

        return [
            'editable' => true,
            'blocked_reason' => null,
            'target' => [
                'type' => $target['type'],
                'id' => $target['id'],
                'label' => $target['label'],
            ],
        ];
    }
}
