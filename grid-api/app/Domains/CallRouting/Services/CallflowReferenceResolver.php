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
     * @param  array<string, array<string, array{id: string, label: string, supports_ring_group_toggle?: bool, supports_ringback?: bool}>>  $targets
     * @return array<string, mixed>
     */
    private function resolveNode(array $node, array $targets): array
    {
        $module = is_string($node['module'] ?? null) ? $node['module'] : 'unknown';
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $isTemporalOperation = $module === 'temporal_route'
            && in_array($data['action'] ?? null, ['disable', 'enable', 'reset'], true);
        $isConferenceService = $module === 'conference'
            && (! is_string($data['id'] ?? null) || $data['id'] === '');
        $isVoicemailCheck = $module === 'voicemail'
            && ($data['action'] ?? null) === 'check'
            && (! is_string($data['id'] ?? null) || $data['id'] === '');
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
        $groupPickupTarget = $module === 'group_pickup'
            ? $this->groupPickupTarget($data)
            : null;
        $pageGroupSettings = $module === 'page_group'
            ? $this->publicPageGroupSettings($data, $targets)
            : null;
        $ringGroupSettings = $module === 'ring_group'
            ? $this->publicRingGroupSettings($data, $targets)
            : null;
        $targetType = match (true) {
            $isConferenceService,
            $isVoicemailCheck,
            $isTemporalOperation,
            $module === 'temporal_route' && $directTemporalRuleIds !== [] => null,
            $module === 'ring_group_toggle' => 'callflow',
            $module === 'group_pickup' => $groupPickupTarget['type'] ?? null,
            $module === 'receive_fax' => 'extension',
            default => $this->targetType($module),
        };
        $resourceId = match ($module) {
            'temporal_route' => is_string($data['rule_set'] ?? null) ? $data['rule_set'] : null,
            'ring_group_toggle' => is_string($data['callflow_id'] ?? null) ? $data['callflow_id'] : null,
            'group_pickup' => $groupPickupTarget['resource_id'] ?? null,
            'receive_fax' => is_string($data['owner_id'] ?? null) ? $data['owner_id'] : null,
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
                $module === 'group_pickup' => $target === null ? 'unresolved' : 'resolved',
                $module === 'page_group' => ($pageGroupSettings['supported_configuration'] ?? false)
                    ? 'resolved'
                    : 'unresolved',
                $module === 'ring_group' => ($ringGroupSettings['supported_configuration'] ?? false)
                    ? 'resolved'
                    : 'unresolved',
                $module === 'ring_group_toggle' => ($target['supports_ring_group_toggle'] ?? false)
                    ? 'resolved'
                    : 'unresolved',
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
            'settings' => $pageGroupSettings
                ?? $ringGroupSettings
                ?? $this->publicInlineSettings($module, $data, $targets),
            'children' => $children,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, array{id: string, label: string, supports_ring_group_toggle?: bool, supports_ringback?: bool}>>  $targets
     * @return array<string, mixed>|null
     */
    private function publicInlineSettings(string $module, array $data, array $targets): ?array
    {
        if ($module === 'conference') {
            return [
                'service_mode' => ! is_string($data['id'] ?? null) || $data['id'] === '',
                'skip_module' => (bool) ($data['skip_module'] ?? false),
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
                'supported_configuration' => ($targets['callflow'][$resourceId]['supports_ring_group_toggle'] ?? false),
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        if ($module === 'acdc_queue') {
            $resourceId = is_string($data['id'] ?? null) ? $data['id'] : null;
            $queue = $resourceId === null ? null : ($targets['queue'][$resourceId] ?? null);

            return [
                'action' => is_string($data['action'] ?? null) ? $data['action'] : null,
                'queue_id' => $queue['id'] ?? null,
                'queue_label' => $queue['label'] ?? null,
                'supported_configuration' => $queue !== null
                    && in_array($data['action'] ?? null, ['login', 'logout'], true),
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        if ($module === 'voicemail') {
            $action = $data['action'] ?? null;

            return is_string($action) && $action !== ''
                ? ['action' => $action, 'skip_module' => (bool) ($data['skip_module'] ?? false)]
                : null;
        }

        if (in_array($module, [
            'acdc_agent',
            'hotdesk',
            'do_not_disturb',
            'call_forward',
        ], true)) {
            $action = $data['action'] ?? null;

            return is_string($action) && $action !== ''
                ? ['action' => $action, 'skip_module' => (bool) ($data['skip_module'] ?? false)]
                : null;
        }

        if (in_array($module, ['eavesdrop', 'eavesdrop_feature'], true)) {
            return ['skip_module' => (bool) ($data['skip_module'] ?? false)];
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

        if ($module === 'set_variables') {
            return $this->publicSetVariablesSettings($data);
        }

        if ($module === 'manual_presence') {
            return [
                'presence_id' => is_string($data['presence_id'] ?? null) ? $data['presence_id'] : '',
                'status' => in_array($data['status'] ?? null, ['idle', 'ringing', 'busy'], true)
                    ? $data['status']
                    : 'idle',
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        if ($module === 'group_pickup') {
            $selection = $this->groupPickupTarget($data);
            $target = $selection === null
                ? null
                : ($targets[$selection['type']][$selection['resource_id']] ?? null);

            return [
                'supported_target' => $selection !== null && $target !== null,
                'target_type' => $selection['type'] ?? null,
                'target_id' => $target['id'] ?? null,
                'target_label' => $target['label'] ?? null,
                'reference_status' => $target === null ? 'unresolved' : 'resolved',
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        if ($module === 'receive_fax') {
            $resourceId = is_string($data['owner_id'] ?? null) && $data['owner_id'] !== ''
                ? $data['owner_id']
                : null;
            $owner = $resourceId === null ? null : ($targets['extension'][$resourceId] ?? null);
            $media = is_array($data['media'] ?? null) ? $data['media'] : [];
            $hasFaxOption = array_key_exists('fax_option', $media);
            $faxOption = $hasFaxOption ? $media['fax_option'] : false;
            $supportedFaxOption = ! $hasFaxOption || is_bool($faxOption) || $faxOption === 'auto';

            return [
                'supported_configuration' => $owner !== null && $supportedFaxOption,
                'owner_id' => $owner['id'] ?? null,
                'owner_label' => $owner['label'] ?? null,
                'reference_status' => $owner === null ? 'unresolved' : 'resolved',
                'fax_option' => $supportedFaxOption ? $faxOption : null,
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        if ($module === 'branch_variable') {
            return $this->publicBranchVariableSettings($data);
        }

        if ($module === 'branch_bnumber') {
            return [
                'hunt' => ($data['hunt'] ?? false) === true,
                'hunt_allow' => is_string($data['hunt_allow'] ?? null) && $data['hunt_allow'] !== ''
                    ? $data['hunt_allow']
                    : null,
                'hunt_deny' => is_string($data['hunt_deny'] ?? null) && $data['hunt_deny'] !== ''
                    ? $data['hunt_deny']
                    : null,
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
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

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, array{id: string, label: string, supports_ring_group_toggle?: bool, supports_ringback?: bool}>>  $targets
     * @return array<string, mixed>
     */
    private function publicPageGroupSettings(array $data, array $targets): array
    {
        $audio = $data['audio'] ?? null;
        $endpoints = $data['endpoints'] ?? null;
        $supported = in_array($audio, ['one-way', 'two-way'], true)
            && ($data['barge_calls'] ?? false) !== true
            && $this->optionalIntegerInRange($data, 'timeout', 1, 30)
            && is_array($endpoints)
            && array_is_list($endpoints)
            && $endpoints !== []
            && count($endpoints) <= 20;
        $deviceIds = [];
        $seen = [];

        foreach ($supported ? $endpoints : [] as $endpoint) {
            $resourceId = is_array($endpoint) && is_string($endpoint['id'] ?? null)
                ? $endpoint['id']
                : null;
            $device = $resourceId === null ? null : ($targets['device'][$resourceId] ?? null);

            if (! is_array($endpoint)
                || ($endpoint['endpoint_type'] ?? null) !== 'device'
                || ! $this->optionalIntegerInRange($endpoint, 'delay', 0, 30)
                || ! $this->optionalIntegerInRange($endpoint, 'timeout', 1, 30)
                || ! $this->optionalIntegerInRange($endpoint, 'weight', 1, 100)
                || $device === null
                || isset($seen[$resourceId])) {
                $supported = false;
                break;
            }

            $seen[$resourceId] = true;
            $deviceIds[] = $device['id'];
        }

        return [
            'supported_configuration' => $supported,
            'audio' => $supported ? $audio : null,
            'device_ids' => $supported ? $deviceIds : [],
            'reference_status' => $supported ? 'resolved' : 'unresolved',
            'skip_module' => (bool) ($data['skip_module'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, array{id: string, label: string, supports_ring_group_toggle?: bool, supports_ringback?: bool}>>  $targets
     * @return array<string, mixed>
     */
    private function publicRingGroupSettings(array $data, array $targets): array
    {
        $strategy = is_string($data['strategy'] ?? null) ? $data['strategy'] : 'simultaneous';
        $repeats = is_int($data['repeats'] ?? null) ? $data['repeats'] : 1;
        $ignoreForward = is_bool($data['ignore_forward'] ?? null) ? $data['ignore_forward'] : true;
        $failOnSingleReject = is_bool($data['fail_on_single_reject'] ?? null)
            ? $data['fail_on_single_reject']
            : false;
        $hasRingback = array_key_exists('ringback', $data);
        $ringbackValue = $data['ringback'] ?? null;
        $ringbackIsDefault = ! $hasRingback || $ringbackValue === null || $ringbackValue === '';
        $ringbackResourceId = is_string($ringbackValue) && $ringbackValue !== ''
            ? $data['ringback']
            : null;
        $ringback = $ringbackResourceId === null
            ? null
            : ($targets['media'][$ringbackResourceId] ?? null);
        $ringbackSupported = $ringbackIsDefault
            || ($ringback['supports_ringback'] ?? false) === true;
        $ringtones = is_array($data['ringtones'] ?? null) ? $data['ringtones'] : [];
        $ringtonesSupported = ! array_key_exists('ringtones', $data)
            || (is_array($data['ringtones'])
                && $this->ringtoneIsSafe($ringtones, 'internal')
                && $this->ringtoneIsSafe($ringtones, 'external'));
        $endpoints = $data['endpoints'] ?? null;
        $supported = in_array($strategy, ['simultaneous', 'single', 'weighted_random'], true)
            && $repeats >= 1
            && $repeats <= RingGroupPolicy::MAX_REPEATS
            && (! array_key_exists('ignore_forward', $data) || is_bool($data['ignore_forward']))
            && (! array_key_exists('fail_on_single_reject', $data) || is_bool($data['fail_on_single_reject']))
            && $ringbackSupported
            && $ringtonesSupported
            && is_array($endpoints)
            && array_is_list($endpoints)
            && $endpoints !== []
            && count($endpoints) <= RingGroupPolicy::MAX_ENDPOINTS;
        $publicEndpoints = [];
        $timings = [];
        $seen = [];

        foreach ($supported ? $endpoints : [] as $endpoint) {
            $resourceId = is_array($endpoint) && is_string($endpoint['id'] ?? null)
                ? $endpoint['id']
                : null;
            $device = $resourceId === null ? null : ($targets['device'][$resourceId] ?? null);
            $delay = is_array($endpoint) && is_int($endpoint['delay'] ?? null)
                ? $endpoint['delay']
                : 0;
            $timeout = is_array($endpoint) && is_int($endpoint['timeout'] ?? null)
                ? $endpoint['timeout']
                : 20;
            $weight = is_array($endpoint) && is_int($endpoint['weight'] ?? null)
                ? $endpoint['weight']
                : null;

            if (! is_array($endpoint)
                || ($endpoint['endpoint_type'] ?? null) !== 'device'
                || $delay < 0
                || $delay > RingGroupPolicy::MAX_ENDPOINT_DELAY
                || $timeout < 1
                || $timeout > RingGroupPolicy::MAX_ENDPOINT_TIMEOUT
                || (in_array($strategy, ['single', 'weighted_random'], true) && $delay !== 0)
                || ! $this->optionalIntegerInRange($endpoint, 'weight', 1, 100)
                || ($strategy === 'weighted_random' && $weight === null)
                || $device === null
                || isset($seen[$resourceId])) {
                $supported = false;
                break;
            }

            $seen[$resourceId] = true;
            $publicEndpoints[] = [
                'device_id' => $device['id'],
                'delay' => $delay,
                'timeout' => $timeout,
                ...($strategy === 'weighted_random' ? ['weight' => $weight] : []),
            ];
            $timings[] = ['delay' => $delay, 'timeout' => $timeout];
        }

        $attemptTimeout = $supported ? RingGroupPolicy::attemptTimeout($strategy, $timings) : null;
        $storedTimeout = is_int($data['timeout'] ?? null) ? $data['timeout'] : 20;
        $supported = $supported
            && $attemptTimeout !== null
            && $attemptTimeout <= RingGroupPolicy::MAX_ATTEMPT_TIMEOUT
            && $storedTimeout === $attemptTimeout;

        return [
            'supported_configuration' => $supported,
            'strategy' => $supported ? $strategy : null,
            'endpoints' => $supported ? $publicEndpoints : [],
            'repeats' => $supported ? $repeats : null,
            'ignore_forward' => $supported ? $ignoreForward : null,
            'fail_on_single_reject' => $supported ? $failOnSingleReject : null,
            'ringback_media_id' => $supported ? ($ringback['id'] ?? null) : null,
            'ringtone_internal' => $supported ? $this->ringtoneValue($ringtones, 'internal') : null,
            'ringtone_external' => $supported ? $this->ringtoneValue($ringtones, 'external') : null,
            'reference_status' => $supported ? 'resolved' : 'unresolved',
            'skip_module' => (bool) ($data['skip_module'] ?? false),
        ];
    }

    /** @param array<string, mixed> $ringtones */
    private function ringtoneIsSafe(array $ringtones, string $key): bool
    {
        if (! array_key_exists($key, $ringtones)) {
            return true;
        }

        $value = $ringtones[$key];

        return is_string($value)
            && ($value === '' || (strlen($value) <= 256 && preg_match('/[\x00\r\n]/', $value) !== 1));
    }

    /** @param array<string, mixed> $ringtones */
    private function ringtoneValue(array $ringtones, string $key): ?string
    {
        $value = $ringtones[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $data */
    private function optionalIntegerInRange(array $data, string $key, int $minimum, int $maximum): bool
    {
        if (! array_key_exists($key, $data)) {
            return true;
        }

        return is_int($data[$key]) && $data[$key] >= $minimum && $data[$key] <= $maximum;
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
    private function publicSetVariablesSettings(array $data): array
    {
        $variables = is_array($data['custom_application_vars'] ?? null)
            ? $data['custom_application_vars']
            : null;
        $supported = $variables !== null
            && count($variables) <= 64
            && (! array_key_exists('export', $data) || is_bool($data['export']));

        if ($supported) {
            foreach ($variables as $key => $value) {
                $name = (string) $key;
                $length = is_string($value)
                    ? (function_exists('mb_strlen') ? mb_strlen($value) : strlen($value))
                    : null;

                if (preg_match('/^[A-Za-z0-9_-]{1,128}$/', $name) !== 1
                    || ! is_int($length)
                    || $length > 1024
                    || preg_match('/[\x00\r\n]/', $value) === 1) {
                    $supported = false;
                    break;
                }
            }
        }

        if (! $supported) {
            return [
                'supported_variables' => false,
                'variable_count' => is_array($variables) ? count($variables) : 0,
                'skip_module' => (bool) ($data['skip_module'] ?? false),
            ];
        }

        return [
            'supported_variables' => true,
            'custom_application_vars' => (object) $variables,
            'export' => ($data['export'] ?? false) === true,
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

    /** @param array<string, mixed> $data @return array{type: string, resource_id: string}|null */
    private function groupPickupTarget(array $data): ?array
    {
        $targets = [];

        foreach (['device_id' => 'device', 'user_id' => 'extension', 'group_id' => 'group'] as $key => $type) {
            if (is_string($data[$key] ?? null) && $data[$key] !== '') {
                $targets[] = ['type' => $type, 'resource_id' => $data[$key]];
            }
        }

        return count($targets) === 1 ? $targets[0] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, array{id: string, label: string, supports_ring_group_toggle?: bool, supports_ringback?: bool}>>  $targets
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
     * @param  array<string, array<string, array{id: string, label: string, supports_ring_group_toggle?: bool, supports_ringback?: bool}>>  $targets
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

    /** @return array<string, array<string, array{id: string, label: string, supports_ring_group_toggle?: bool, supports_ringback?: bool}>> */
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
                    'supports_ring_group_toggle' => $callflow->canBeRingGroupToggleTarget(),
                ],
            ])->all(),
            'media' => $account->media()->get()->mapWithKeys(fn ($media): array => [
                $media->switch_resource_id => [
                    'id' => $media->id,
                    'label' => $media->name ?? 'Unnamed media',
                    'supports_ringback' => $media->streamable === true
                        && is_string($media->content_type)
                        && str_starts_with($media->content_type, 'audio/'),
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
