<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\Organizations\Models\SwitchAccount;

class CallflowReferenceResolver
{
    /**
     * @param  array<string, mixed>|null  $flow
     * @return array<string, mixed>|null
     */
    public function resolve(SwitchAccount $account, ?array $flow): ?array
    {
        if ($flow === null || ! is_string($flow['module'] ?? null)) {
            return null;
        }

        return $this->resolveNode($flow, $this->targetMaps($account));
    }

    public function refresh(SwitchAccount $account): void
    {
        $targets = $this->targetMaps($account);

        foreach ($account->callflows()->get() as $callflow) {
            $flow = $callflow->switch_json['flow'] ?? null;
            $callflow->forceFill([
                'flow_structure' => is_array($flow) ? $this->resolveNode($flow, $targets) : null,
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<string, array{id: string, label: string}>>  $targets
     * @return array<string, mixed>
     */
    private function resolveNode(array $node, array $targets): array
    {
        $module = is_string($node['module'] ?? null) ? $node['module'] : 'unknown';
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $directTemporalRuleIds = $module === 'temporal_route' && is_array($data['rules'] ?? null)
            ? array_values(array_filter($data['rules'], fn (mixed $id): bool => is_string($id) && $id !== ''))
            : [];
        $directTemporalRules = array_map(
            fn (string $id, int $position): array => ($rule = $targets['temporal_rule'][$id] ?? null) === null
                ? [
                    'id' => null,
                    'label' => 'Unresolved Temporal Rule',
                    'position' => $position,
                    'resolved' => false,
                ]
                : [
                    'id' => $rule['id'],
                    'label' => $rule['label'],
                    'position' => $position,
                    'resolved' => true,
                ],
            $directTemporalRuleIds,
            array_keys($directTemporalRuleIds),
        );
        $targetType = $module === 'temporal_route' && $directTemporalRuleIds !== []
            ? null
            : $this->targetType($module);
        $resourceId = match ($module) {
            'temporal_route' => is_string($data['rule_set'] ?? null) ? $data['rule_set'] : null,
            'faxbox' => is_string($data['id'] ?? null) ? $data['id'] : (is_string($data['faxbox_id'] ?? null) ? $data['faxbox_id'] : null),
            default => is_string($data['id'] ?? null) ? $data['id'] : null,
        };
        $target = $targetType !== null && $resourceId !== null
            ? ($targets[$targetType][$resourceId] ?? null)
            : null;
        $children = [];

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $branch => $child) {
            if ((is_string($branch) || is_int($branch)) && is_array($child)) {
                $children[(string) $branch] = $this->resolveNode($child, $targets);
            }
        }

        return [
            'module' => $module,
            'target' => $targetType === null || $target === null ? null : [
                'type' => $targetType,
                'id' => $target['id'],
                'label' => $target['label'],
            ],
            'reference_status' => match (true) {
                $module === 'cidlistmatch'
                    && is_string($data['id'] ?? null)
                    && isset($targets['caller_id_list'][$data['id']]) => 'resolved',
                $module === 'cidlistmatch' => 'unresolved',
                $directTemporalRuleIds !== []
                    && ! in_array(false, array_column($directTemporalRules, 'resolved'), true) => 'resolved',
                $directTemporalRuleIds !== [] => 'unresolved',
                $targetType === null => 'not_applicable',
                $target !== null => 'resolved',
                default => 'unresolved',
            },
            'temporal_rules' => $directTemporalRules,
            'settings' => $this->publicInlineSettings($module, $data, $targets),
            'children' => $children,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, array{id: string, label: string}>>  $targets
     * @return array<string, mixed>|null
     */
    private function publicInlineSettings(string $module, array $data, array $targets): ?array
    {
        if ($module === 'conference') {
            return [
                'service_mode' => ! is_string($data['id'] ?? null) || $data['id'] === '',
            ];
        }

        if ($module === 'temporal_route' && is_array($data['rules'] ?? null)) {
            return [
                'action' => is_string($data['action'] ?? null) ? $data['action'] : null,
                'rules' => array_values(array_filter(array_map(
                    fn (mixed $id): ?string => is_string($id)
                        ? ($targets['temporal_rule'][$id]['id'] ?? null)
                        : null,
                    $data['rules'],
                ))),
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        if ($module === 'ring_group_toggle') {
            $resourceId = is_string($data['callflow_id'] ?? null) ? $data['callflow_id'] : null;

            return [
                'action' => is_string($data['action'] ?? null) ? $data['action'] : null,
                'callflow_id' => $resourceId === null ? null : ($targets['callflow'][$resourceId]['id'] ?? null),
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        if ($module === 'voicemail') {
            $action = $data['action'] ?? null;

            return is_string($action) && $action !== '' ? ['action' => $action] : null;
        }

        if (in_array($module, [
            'hotdesk',
            'do_not_disturb',
            'call_forward',
        ], true)) {
            $action = $data['action'] ?? null;

            return is_string($action) && $action !== ''
                ? ['action' => $action, 'skip_module' => (bool) ($data['skip_module'] ?? false)]
                : null;
        }

        if ($module === 'missed_call_alert') {
            return [
                'recipients' => $this->publicMissedCallAlertRecipients($data['recipients'] ?? null, $targets),
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        if ($module === 'check_cid') {
            return $this->publicCheckCidSettings($data, $targets);
        }

        if ($module === 'cidlistmatch') {
            $rawListId = is_string($data['id'] ?? null) && $data['id'] !== '' ? $data['id'] : null;
            $list = $rawListId === null ? null : ($targets['caller_id_list'][$rawListId] ?? null);

            return [
                'caller_id_list_id' => $list['id'] ?? null,
                'caller_id_list_label' => $list['label'] ?? null,
                'reference_status' => $list === null ? 'unresolved' : 'resolved',
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        if ($module === 'set_variable') {
            return $this->publicSetVariableSettings($data);
        }

        if ($module === 'branch_variable') {
            return $this->publicBranchVariableSettings($data);
        }

        $keys = match ($module) {
            'sleep' => ['duration', 'unit', 'skip_module'],
            'tts' => ['text', 'voice', 'language', 'engine', 'endless_playback', 'terminators', 'skip_module'],
            'collect_dtmf' => ['collection_name', 'interdigit_timeout', 'max_digits', 'terminators', 'terminator', 'timeout', 'skip_module'],
            'record_call' => [
                'action', 'format', 'label', 'record_min_sec', 'record_on_answer', 'record_on_bridge',
                'record_sample_rate', 'should_follow_transfer', 'time_limit', 'skip_module',
            ],
            'record_caller' => ['format', 'time_limit', 'skip_module'],
            'send_dtmf' => ['digits', 'duration_ms', 'skip_module'],
            'flush_dtmf' => ['collection_name', 'skip_module'],
            'dead_air' => ['skip_module'],
            'language' => ['language', 'skip_module'],
            'response' => ['code', 'message', 'skip_module'],
            'hangup' => ['skip_module'],
            'set_cid' => ['caller_id_name', 'caller_id_number', 'skip_module'],
            'prepend_cid' => [
                'action', 'apply_to', 'caller_id_name_prefix', 'caller_id_number_prefix', 'skip_module',
            ],
            'set_alert_info' => ['alert_info', 'skip_module'],
            default => [],
        };

        if ($keys === []) {
            return null;
        }

        $settings = [];

        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_scalar($value) || is_array($value)) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function publicSetVariableSettings(array $data): array
    {
        $value = is_string($data['value'] ?? null) ? $data['value'] : null;
        $channel = is_string($data['channel'] ?? null) ? $data['channel'] : 'a';
        $supported = ($data['variable'] ?? null) === 'call_priority'
            && $value !== null
            && preg_match('/^\d{1,3}$/', $value) === 1
            && (int) $value <= 255
            && in_array($channel, ['a', 'both'], true);

        if (! $supported) {
            return [
                'supported_variable' => false,
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        return [
            'supported_variable' => true,
            'variable' => 'call_priority',
            'value' => $value,
            'channel' => $channel,
            'skip_module' => (bool) ($data['skip_module'] ?? false),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function publicBranchVariableSettings(array $data): array
    {
        $supported = ($data['variable'] ?? null) === 'call_priority'
            && in_array($data['scope'] ?? 'custom_channel_vars', ['custom_channel_vars'], true);

        if (! $supported) {
            return [
                'supported_variable' => false,
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        return [
            'supported_variable' => true,
            'variable' => 'call_priority',
            'scope' => 'custom_channel_vars',
            'skip_module' => (bool) ($data['skip_module'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, array{id: string, label: string}>>  $targets
     * @return array<string, mixed>
     */
    private function publicCheckCidSettings(array $data, array $targets): array
    {
        $external = is_array($data['caller_id'] ?? null)
            && is_array($data['caller_id']['external'] ?? null)
                ? $data['caller_id']['external']
                : [];
        $name = is_string($external['name'] ?? null) ? $external['name'] : null;
        $number = is_string($external['number'] ?? null) ? $external['number'] : null;
        $rawUserId = is_string($data['user_id'] ?? null) && $data['user_id'] !== ''
            ? $data['user_id']
            : null;
        $user = $rawUserId === null ? null : ($targets['extension'][$rawUserId] ?? null);
        $identityConfigured = $name !== null || $number !== null || $rawUserId !== null;

        return [
            'regex' => is_string($data['regex'] ?? null) && $data['regex'] !== '' ? $data['regex'] : '.*',
            'use_absolute_mode' => ($data['use_absolute_mode'] ?? false) === true,
            'external_caller_id_name' => $name,
            'external_caller_id_number' => $number,
            'user_id' => $user['id'] ?? null,
            'identity_reference_status' => match (true) {
                ! $identityConfigured => 'not_applicable',
                $user !== null => 'resolved',
                default => 'unresolved',
            },
            'skip_module' => (bool) ($data['skip_module'] ?? false),
        ];
    }

    /**
     * @param  array<string, array<string, array{id: string, label: string}>>  $targets
     * @return list<array{type: string, id: string}>
     */
    private function publicMissedCallAlertRecipients(mixed $value, array $targets): array
    {
        if (! is_array($value)) {
            return [];
        }

        $recipients = [];

        foreach ($value as $recipient) {
            if (! is_array($recipient) || ! is_string($recipient['type'] ?? null)) {
                continue;
            }

            $ids = is_array($recipient['id'] ?? null) ? $recipient['id'] : [$recipient['id'] ?? null];

            foreach ($ids as $id) {
                if (! is_string($id) || $id === '') {
                    continue;
                }

                if ($recipient['type'] === 'email' && filter_var($id, FILTER_VALIDATE_EMAIL) !== false) {
                    $recipients[] = ['type' => 'email', 'id' => $id];
                }

                if ($recipient['type'] === 'user' && isset($targets['extension'][$id])) {
                    $recipients[] = ['type' => 'user', 'id' => $targets['extension'][$id]['id']];
                }
            }
        }

        return $recipients;
    }

    /** @return array<string, array<string, array{id: string, label: string}>> */
    private function targetMaps(SwitchAccount $account): array
    {
        return [
            'extension' => $account->extensions()->get()->mapWithKeys(fn ($extension): array => [
                $extension->switch_resource_id => [
                    'id' => $extension->id,
                    'label' => $extension->display_name ?? $extension->extension ?? 'Unnamed extension',
                ],
            ])->all(),
            'device' => $account->devices()->get()->mapWithKeys(fn ($device): array => [
                $device->switch_resource_id => [
                    'id' => $device->id,
                    'label' => $device->name ?? 'Unnamed device',
                ],
            ])->all(),
            'voicemail' => $account->voicemailBoxes()->get()->mapWithKeys(fn ($box): array => [
                $box->switch_resource_id => [
                    'id' => $box->id,
                    'label' => $box->name ?? $box->mailbox ?? 'Unnamed mailbox',
                ],
            ])->all(),
            'callflow' => $account->callflows()->get()->mapWithKeys(fn ($callflow): array => [
                $callflow->switch_resource_id => [
                    'id' => $callflow->id,
                    'label' => $callflow->name ?? ($callflow->numbers[0] ?? 'Unnamed route'),
                ],
            ])->all(),
            'media' => $account->media()->get()->mapWithKeys(fn ($media): array => [
                $media->switch_resource_id => [
                    'id' => $media->id,
                    'label' => $media->name ?? 'Unnamed media',
                ],
            ])->all(),
            'directory' => $account->directories()->get()->mapWithKeys(fn ($directory): array => [
                $directory->switch_resource_id => [
                    'id' => $directory->id,
                    'label' => $directory->name,
                ],
            ])->all(),
            'group' => $account->groups()->get()->mapWithKeys(fn ($group): array => [
                $group->switch_resource_id => [
                    'id' => $group->id,
                    'label' => $group->name,
                ],
            ])->all(),
            'queue' => $account->queues()->get()->mapWithKeys(fn ($queue): array => [
                $queue->switch_resource_id => [
                    'id' => $queue->id,
                    'label' => $queue->name,
                ],
            ])->all(),
            'menu' => $account->menus()->get()->mapWithKeys(fn ($menu): array => [
                $menu->switch_resource_id => ['id' => $menu->id, 'label' => $menu->name],
            ])->all(),
            'conference' => $account->conferences()->get()->mapWithKeys(fn ($conference): array => [
                $conference->switch_resource_id => ['id' => $conference->id, 'label' => $conference->name],
            ])->all(),
            'fax_box' => $account->faxBoxes()->get()->mapWithKeys(fn ($box): array => [
                $box->switch_resource_id => ['id' => $box->id, 'label' => $box->name],
            ])->all(),
            'temporal_rule_set' => $account->temporalRuleSets()->get()->mapWithKeys(fn ($set): array => [
                $set->switch_resource_id => ['id' => $set->id, 'label' => $set->name],
            ])->all(),
            'temporal_rule' => $account->temporalRules()->get()->mapWithKeys(fn ($rule): array => [
                $rule->switch_resource_id => ['id' => $rule->id, 'label' => $rule->name],
            ])->all(),
            'caller_id_list' => $account->callerIdLists()->get()->mapWithKeys(fn ($list): array => [
                $list->switch_resource_id => ['id' => $list->id, 'label' => $list->name],
            ])->all(),
        ];
    }

    private function targetType(string $module): ?string
    {
        return match ($module) {
            'user' => 'extension',
            'device' => 'device',
            'voicemail' => 'voicemail',
            'callflow' => 'callflow',
            'play' => 'media',
            'directory' => 'directory',
            'group' => 'group',
            'acdc_member', 'acdc_queue' => 'queue',
            'menu' => 'menu',
            'conference' => 'conference',
            'faxbox' => 'fax_box',
            'temporal_route' => 'temporal_rule_set',
            default => null,
        };
    }
}
