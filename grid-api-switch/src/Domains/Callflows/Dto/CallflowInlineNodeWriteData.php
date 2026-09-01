<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use GridPbx\Switch\Domains\Callflows\Support\CallflowBranchPolicy;
use InvalidArgumentException;

/**
 * Adds or updates one schema-backed inline action while preserving unknown data and its subtree.
 */
final readonly class CallflowInlineNodeWriteData
{
    private const PLACEMENTS = ['append', 'insert_before', 'replace'];

    /** @var array<string, list<string>> */
    private const MANAGED_KEYS = [
        'sleep' => ['duration', 'unit', 'skip_module'],
        'tts' => ['text', 'voice', 'language', 'engine', 'endless_playback', 'terminators', 'skip_module'],
        'collect_dtmf' => ['collection_name', 'interdigit_timeout', 'max_digits', 'terminators', 'timeout', 'skip_module'],
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
        'set_variable' => ['variable', 'value', 'channel', 'skip_module'],
        'set_variables' => ['custom_application_vars', 'export', 'skip_module'],
        'manual_presence' => ['presence_id', 'status', 'skip_module'],
        'group_pickup' => ['device_id', 'user_id', 'group_id', 'skip_module'],
        'page_group' => ['audio', 'endpoints', 'skip_module'],
        'ring_group' => [
            'strategy', 'endpoints', 'repeats', 'timeout', 'ignore_forward',
            'fail_on_single_reject', 'ringback', 'ringtones', 'skip_module',
        ],
        'receive_fax' => ['owner_id', 'media', 'skip_module'],
        'conference' => ['skip_module'],
        'voicemail' => ['action', 'skip_module'],
        'branch_variable' => ['variable', 'scope', 'skip_module'],
        'branch_bnumber' => ['hunt', 'hunt_allow', 'hunt_deny', 'skip_module'],
        'missed_call_alert' => ['recipients', 'skip_module'],
        'set_cid' => ['caller_id_name', 'caller_id_number', 'skip_module'],
        'prepend_cid' => [
            'action', 'apply_to', 'caller_id_name_prefix', 'caller_id_number_prefix', 'skip_module',
        ],
        'set_alert_info' => ['alert_info', 'skip_module'],
        'check_cid' => ['regex', 'use_absolute_mode', 'caller_id', 'user_id', 'skip_module'],
        'cidlistmatch' => ['id', 'skip_module'],
        'temporal_route' => ['action', 'rules', 'skip_module'],
        'ring_group_toggle' => ['action', 'callflow_id', 'skip_module'],
        'acdc_queue' => ['action', 'id', 'skip_module'],
        'hotdesk' => ['action', 'skip_module'],
        'do_not_disturb' => ['action', 'skip_module'],
    ];

    /**
     * @param  array<string, mixed>  $current
     * @param  list<string>  $path
     * @param  array<string, mixed>  $settings
     */
    private function __construct(
        private array $current,
        private string $operation,
        private array $path,
        private ?string $branch,
        private string $placement,
        public string $module,
        private array $settings,
    ) {
        if (! is_array($this->current['flow'] ?? null)) {
            throw new InvalidArgumentException('Switch callflow must contain a root flow node before its tree can be edited.');
        }

        if (! array_key_exists($this->module, self::MANAGED_KEYS)) {
            throw new InvalidArgumentException('The inline Switch callflow action is not supported.');
        }

        if (! in_array($this->placement, self::PLACEMENTS, true)) {
            throw new InvalidArgumentException('The inline action placement is invalid.');
        }

        $this->assertPublicPath($this->path);
        $this->assertSettings();

        /** @var array<string, mixed> $flow */
        $flow = $this->current['flow'];

        if ($this->operation === 'create') {
            if ($this->branch === null || ! CallflowBranchPolicy::isPublicKey($this->branch)) {
                throw new InvalidArgumentException('The destination branch is not editable.');
            }
            $parent = $this->nodeAt($flow, $this->path, 'parent');

            if (CallflowBranchPolicy::childrenAreLocked($parent)) {
                throw new InvalidArgumentException('This conditional action has preserved branches that cannot be edited.');
            }

            if (! CallflowBranchPolicy::supports($parent, $this->branch)) {
                throw new InvalidArgumentException('The destination branch is not valid for the selected callflow node.');
            }

            $children = is_array($parent['children'] ?? null) ? $parent['children'] : [];

            $occupied = array_key_exists($this->branch, $children);

            if ($this->placement === 'append' && $occupied) {
                throw new InvalidArgumentException('The destination callflow branch is already occupied.');
            }

            if ($this->placement !== 'append' && ! $occupied) {
                throw new InvalidArgumentException('The destination callflow branch is no longer occupied.');
            }

            if ($this->placement !== 'append' && $this->branch !== '_') {
                throw new InvalidArgumentException('Only an occupied continuation branch can be inserted into or replaced.');
            }

            if ($this->placement === 'insert_before'
                && CallflowBranchPolicy::isTerminalModule($this->module)) {
                throw new InvalidArgumentException('A terminal action cannot preserve the existing next step.');
            }

            return;
        }

        if ($this->path === [] && $this->module !== 'ring_group') {
            throw new InvalidArgumentException('Only a supported Ring Group may be edited as an inline root action.');
        }
        $node = $this->nodeAt($flow, $this->path, 'node');

        if (($node['module'] ?? null) !== $this->module) {
            throw new InvalidArgumentException('The selected inline action module changed and must be reloaded.');
        }

        if ($this->module === 'check_cid') {
            $currentData = is_array($node['data'] ?? null) ? $node['data'] : [];

            if (($currentData['use_absolute_mode'] ?? false) === true) {
                throw new InvalidArgumentException('Absolute-mode caller ID checks are preserved but cannot be edited.');
            }
        }

        if ($this->module === 'set_variable') {
            $currentData = is_array($node['data'] ?? null) ? $node['data'] : [];

            if (($currentData['variable'] ?? null) !== 'call_priority') {
                throw new InvalidArgumentException('The existing inline channel variable is not supported.');
            }
        }

        if ($this->module === 'group_pickup') {
            $currentData = is_array($node['data'] ?? null) ? $node['data'] : [];
            $targets = array_filter(
                ['device_id', 'user_id', 'group_id'],
                fn (string $key): bool => is_string($currentData[$key] ?? null)
                    && $currentData[$key] !== '',
            );

            if (count($targets) !== 1) {
                throw new InvalidArgumentException('The existing Group Pickup target is not supported.');
            }
        }

        if ($this->module === 'receive_fax') {
            $currentData = is_array($node['data'] ?? null) ? $node['data'] : [];

            if (! is_string($currentData['owner_id'] ?? null) || $currentData['owner_id'] === '') {
                throw new InvalidArgumentException('The existing Receive Fax owner is not supported.');
            }
        }

        if ($this->module === 'page_group') {
            $currentData = is_array($node['data'] ?? null) ? $node['data'] : [];
            $this->assertEditablePageGroup($currentData);
        }

        if ($this->module === 'ring_group') {
            $currentData = is_array($node['data'] ?? null) ? $node['data'] : [];
            $this->assertEditableRingGroup($currentData);
        }

        if ($this->module === 'conference') {
            $currentData = is_array($node['data'] ?? null) ? $node['data'] : [];

            if (is_string($currentData['id'] ?? null) && $currentData['id'] !== '') {
                throw new InvalidArgumentException('Configured conference destinations must be edited through the destination editor.');
            }
        }

        if ($this->module === 'voicemail') {
            $currentData = is_array($node['data'] ?? null) ? $node['data'] : [];

            if (($currentData['action'] ?? null) !== 'check'
                || (is_string($currentData['id'] ?? null) && $currentData['id'] !== '')) {
                throw new InvalidArgumentException('Configured voicemail destinations must be edited through the destination editor.');
            }
        }

        if ($this->module === 'branch_variable'
            && ! CallflowBranchPolicy::supportsCallPriority($node)) {
            throw new InvalidArgumentException('The existing branch variable is not supported.');
        }

        if ($this->module === 'branch_bnumber' && ($this->settings['hunt'] ?? false) === true) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $exactBranches = array_filter(array_keys($children), fn (string|int $key): bool => (string) $key !== '_');

            if ($exactBranches !== []) {
                throw new InvalidArgumentException('Remove exact captured-number branches before enabling hunt mode.');
            }
        }
    }

    /** @param array<string, mixed> $current @param list<string> $parentPath @param array<string, mixed> $settings */
    public static function create(
        array $current,
        array $parentPath,
        string $branch,
        string $module,
        array $settings,
        string $placement = 'append',
    ): self {
        return new self($current, 'create', $parentPath, $branch, $placement, $module, $settings);
    }

    /** @param array<string, mixed> $current @param list<string> $nodePath @param array<string, mixed> $settings */
    public static function update(
        array $current,
        array $nodePath,
        string $module,
        array $settings,
    ): self {
        return new self($current, 'update', $nodePath, null, 'append', $module, $settings);
    }

    /**
     * Builds one validated inline action for use as a new callflow root.
     *
     * The temporary parent intentionally uses the ordinary continuation branch so root actions
     * receive the exact same schema validation and Switch normalization as actions inserted into
     * an existing callflow tree.
     *
     * @param  array<string, mixed>  $settings
     * @return array{module: string, data: array<string, mixed>, children: object}
     */
    public static function rootNode(string $module, array $settings): array
    {
        $write = self::create(
            current: [
                'flow' => [
                    'module' => 'callflow',
                    'data' => [],
                    'children' => (object) [],
                ],
            ],
            parentPath: [],
            branch: '_',
            module: $module,
            settings: $settings,
        );
        $document = $write->toSwitchData();
        $children = $document['flow']['children'] ?? null;
        $node = is_object($children) ? get_object_vars($children)['_'] ?? null : null;

        if (! is_array($node)) {
            throw new InvalidArgumentException('The inline Switch callflow root could not be built.');
        }

        return $node;
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = $this->withoutPrivateDocumentFields($this->current);
        /** @var array<string, mixed> $flow */
        $flow = $data['flow'];

        if ($this->operation === 'create' && $this->branch !== null) {
            $this->insertAt($flow, $this->path, $this->branch, $this->placement);
        } else {
            $this->updateAt($flow, $this->path);
        }

        $data['flow'] = $this->normalizeNodeForJson($flow);

        return $data;
    }

    private function assertSettings(): void
    {
        $allowed = self::MANAGED_KEYS[$this->module];

        foreach (array_keys($this->settings) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('The inline action contains an unsupported setting.');
            }
        }

        match ($this->module) {
            'sleep' => $this->assertSleep(),
            'tts' => $this->assertTts(),
            'collect_dtmf' => $this->assertCollectDtmf(),
            'record_call' => $this->assertRecordCall(),
            'record_caller' => $this->assertRecordCaller(),
            'send_dtmf' => $this->assertSendDtmf(),
            'flush_dtmf' => $this->assertFlushDtmf(),
            'dead_air' => null,
            'language' => $this->assertLanguage(),
            'response' => $this->assertResponse(),
            'hangup' => null,
            'set_variable' => $this->assertSetVariable(),
            'set_variables' => $this->assertSetVariables(),
            'manual_presence' => $this->assertManualPresence(),
            'group_pickup' => $this->assertGroupPickup(),
            'page_group' => $this->assertPageGroup(),
            'ring_group' => $this->assertRingGroup(),
            'receive_fax' => $this->assertReceiveFax(),
            'conference' => null,
            'voicemail' => $this->oneOf('action', ['check']),
            'branch_variable' => $this->assertBranchVariable(),
            'branch_bnumber' => $this->assertBranchBnumber(),
            'missed_call_alert' => $this->assertMissedCallAlert(),
            'set_cid' => $this->assertSetCid(),
            'prepend_cid' => $this->assertPrependCid(),
            'set_alert_info' => $this->assertSetAlertInfo(),
            'check_cid' => $this->assertCheckCid(),
            'cidlistmatch' => $this->string('id', 1, 128),
            'temporal_route' => $this->assertTemporalRouteOperation(),
            'ring_group_toggle' => $this->assertRingGroupToggle(),
            'acdc_queue' => $this->assertAcdcQueue(),
            'hotdesk' => $this->oneOf('action', ['login', 'logout', 'toggle']),
            'do_not_disturb' => $this->oneOf('action', ['activate', 'deactivate', 'toggle']),
        };

        if (array_key_exists('skip_module', $this->settings) && ! is_bool($this->settings['skip_module'])) {
            throw new InvalidArgumentException('The inline action skip setting must be boolean.');
        }
    }

    private function assertSleep(): void
    {
        $this->integer('duration', 0, 86400000);
        $this->oneOf('unit', ['ms', 's', 'm', 'h']);
    }

    private function assertTts(): void
    {
        $this->string('text', 1, 1000);
        $this->nullableString('voice', 64);
        $this->nullableString('language', 35);
        $this->nullableOneOf('engine', ['flite', 'google', 'ispeech', 'voicefabric']);
        $this->boolean('endless_playback');
        $this->dtmfList('terminators');
    }

    private function assertCollectDtmf(): void
    {
        $this->nullableString('collection_name', 128);
        $this->integer('interdigit_timeout', 1, 86400000);
        $this->integer('max_digits', 1, 128);
        $this->dtmfList('terminators');
        $this->integer('timeout', 1, 86400000);
    }

    private function assertRecordCall(): void
    {
        $this->oneOf('action', ['start', 'stop']);
        $this->nullableOneOf('format', ['mp3', 'wav']);
        $this->nullableString('label', 128);
        $this->nullableInteger('record_min_sec', 0, 10800);
        $this->boolean('record_on_answer');
        $this->boolean('record_on_bridge');
        $this->nullableInteger('record_sample_rate', 8000, 192000);
        $this->boolean('should_follow_transfer');
        $this->integer('time_limit', 5, 10800);
    }

    private function assertRecordCaller(): void
    {
        $this->nullableOneOf('format', ['mp3', 'wav']);
        $this->integer('time_limit', 5, 10800);
    }

    private function assertSendDtmf(): void
    {
        $this->string('digits', 1, 128);
        $this->integer('duration_ms', 1, 60000);
    }

    private function assertFlushDtmf(): void
    {
        $this->string('collection_name', 1, 128);
    }

    private function assertLanguage(): void
    {
        $this->string('language', 2, 5);

        if (preg_match('/^[A-Za-z]{2}(?:-[A-Za-z]{2})?$/', $this->settings['language']) !== 1) {
            throw new InvalidArgumentException('The inline action language setting is invalid.');
        }
    }

    private function assertResponse(): void
    {
        $this->integer('code', 400, 699);
        $this->nullableString('message', 128);
    }

    private function assertSetVariable(): void
    {
        if (($this->settings['variable'] ?? null) !== 'call_priority') {
            throw new InvalidArgumentException('The inline action variable setting is not supported.');
        }

        $this->string('value', 1, 3);

        if (preg_match('/^\d{1,3}$/', $this->settings['value']) !== 1
            || (int) $this->settings['value'] > 255) {
            throw new InvalidArgumentException('The inline action value setting is invalid.');
        }

        $this->oneOf('channel', ['a', 'both']);
    }

    private function assertSetVariables(): void
    {
        $variables = $this->settings['custom_application_vars'] ?? null;

        if (! is_array($variables) || count($variables) > 64) {
            throw new InvalidArgumentException('The inline action custom application variables are invalid.');
        }

        foreach ($variables as $key => $value) {
            $name = (string) $key;
            $length = is_string($value)
                ? (function_exists('mb_strlen') ? mb_strlen($value) : strlen($value))
                : null;

            if (preg_match('/^[A-Za-z0-9_-]{1,128}$/', $name) !== 1
                || ! is_int($length)
                || $length > 1024
                || str_contains($value, "\0")
                || str_contains($value, "\r")
                || str_contains($value, "\n")) {
                throw new InvalidArgumentException('The inline action custom application variable is invalid.');
            }
        }

        $this->boolean('export');
    }

    private function assertManualPresence(): void
    {
        $this->string('presence_id', 1, 256);
        $this->oneOf('status', ['idle', 'ringing', 'busy']);

        $presenceId = $this->settings['presence_id'];

        if (trim($presenceId) !== $presenceId
            || preg_match('/^[^\s@]+(?:@[^\s@]+)?$/u', $presenceId) !== 1) {
            throw new InvalidArgumentException('The inline action presence identifier is invalid.');
        }
    }

    private function assertGroupPickup(): void
    {
        $targets = array_filter(
            ['device_id', 'user_id', 'group_id'],
            fn (string $key): bool => array_key_exists($key, $this->settings),
        );

        if (count($targets) !== 1) {
            throw new InvalidArgumentException('The inline Group Pickup action must contain exactly one target.');
        }

        $this->string(array_values($targets)[0], 1, 128);
    }

    private function assertPageGroup(): void
    {
        $this->oneOf('audio', ['one-way', 'two-way']);
        $endpoints = $this->settings['endpoints'] ?? null;

        if (! is_array($endpoints) || ! array_is_list($endpoints)
            || $endpoints === [] || count($endpoints) > 20) {
            throw new InvalidArgumentException('The inline Page Group endpoint selection is invalid.');
        }

        $ids = [];

        foreach ($endpoints as $endpoint) {
            if (! is_array($endpoint)
                || array_diff(array_keys($endpoint), ['endpoint_type', 'id']) !== []
                || ($endpoint['endpoint_type'] ?? null) !== 'device'
                || ! is_string($endpoint['id'] ?? null)
                || $endpoint['id'] === ''
                || strlen($endpoint['id']) > 128
                || in_array($endpoint['id'], $ids, true)) {
                throw new InvalidArgumentException('The inline Page Group endpoint selection is invalid.');
            }

            $ids[] = $endpoint['id'];
        }
    }

    /** @param array<string, mixed> $data */
    private function assertEditablePageGroup(array $data): void
    {
        $endpoints = $data['endpoints'] ?? null;

        if (! in_array($data['audio'] ?? null, ['one-way', 'two-way'], true)
            || ($data['barge_calls'] ?? false) === true
            || ! $this->optionalIntegerInRange($data, 'timeout', 1, 30)
            || ! is_array($endpoints)
            || ! array_is_list($endpoints)
            || $endpoints === []
            || count($endpoints) > 20) {
            throw new InvalidArgumentException('The existing Page Group configuration is not supported.');
        }

        $ids = [];

        foreach ($endpoints as $endpoint) {
            if (! is_array($endpoint)
                || ($endpoint['endpoint_type'] ?? null) !== 'device'
                || ! is_string($endpoint['id'] ?? null)
                || $endpoint['id'] === ''
                || strlen($endpoint['id']) > 128
                || ! $this->optionalIntegerInRange($endpoint, 'delay', 0, 30)
                || ! $this->optionalIntegerInRange($endpoint, 'timeout', 1, 30)
                || ! $this->optionalIntegerInRange($endpoint, 'weight', 1, 100)
                || in_array($endpoint['id'], $ids, true)) {
                throw new InvalidArgumentException('The existing Page Group configuration is not supported.');
            }

            $ids[] = $endpoint['id'];
        }
    }

    private function assertRingGroup(): void
    {
        $this->oneOf('strategy', ['simultaneous', 'single', 'weighted_random']);
        $this->integer('repeats', 1, 3);
        $this->integer('timeout', 1, 120);
        $this->boolean('ignore_forward');
        $this->boolean('fail_on_single_reject');
        $this->assertRingGroupMedia();
        $endpoints = $this->settings['endpoints'] ?? null;

        if (! is_array($endpoints) || ! array_is_list($endpoints)
            || $endpoints === [] || count($endpoints) > 20) {
            throw new InvalidArgumentException('The inline Ring Group endpoint selection is invalid.');
        }

        $ids = [];
        $timings = [];

        foreach ($endpoints as $endpoint) {
            if (! is_array($endpoint)
                || array_diff(array_keys($endpoint), ['endpoint_type', 'id', 'delay', 'timeout', 'weight']) !== []
                || ! in_array($endpoint['endpoint_type'] ?? null, ['device', 'user', 'group'], true)
                || ! is_string($endpoint['id'] ?? null)
                || $endpoint['id'] === ''
                || strlen($endpoint['id']) > 128
                || ! is_int($endpoint['delay'] ?? null)
                || $endpoint['delay'] < 0
                || $endpoint['delay'] > 60
                || ! is_int($endpoint['timeout'] ?? null)
                || $endpoint['timeout'] < 1
                || $endpoint['timeout'] > 60
                || (in_array($this->settings['strategy'], ['single', 'weighted_random'], true)
                    && $endpoint['delay'] !== 0)
                || ($this->settings['strategy'] === 'weighted_random'
                    && (! array_key_exists('weight', $endpoint)
                        || ! $this->optionalIntegerInRange($endpoint, 'weight', 1, 100)))
                || ($this->settings['strategy'] !== 'weighted_random'
                    && array_key_exists('weight', $endpoint))
                || in_array($endpoint['endpoint_type'].':'.$endpoint['id'], $ids, true)) {
                throw new InvalidArgumentException('The inline Ring Group endpoint selection is invalid.');
            }

            $ids[] = $endpoint['endpoint_type'].':'.$endpoint['id'];
            $timings[] = ['delay' => $endpoint['delay'], 'timeout' => $endpoint['timeout']];
        }

        if ($this->ringGroupAttemptTimeout($this->settings['strategy'], $timings) !== $this->settings['timeout']) {
            throw new InvalidArgumentException('The inline Ring Group timeout does not match its endpoints.');
        }
    }

    /** @param array<string, mixed> $data */
    private function assertEditableRingGroup(array $data): void
    {
        $strategy = is_string($data['strategy'] ?? null) ? $data['strategy'] : 'simultaneous';
        $repeats = is_int($data['repeats'] ?? null) ? $data['repeats'] : 1;
        $endpoints = $data['endpoints'] ?? null;

        if (! in_array($strategy, ['simultaneous', 'single', 'weighted_random'], true)
            || $repeats < 1
            || $repeats > 3
            || (array_key_exists('ignore_forward', $data) && ! is_bool($data['ignore_forward']))
            || (array_key_exists('fail_on_single_reject', $data) && ! is_bool($data['fail_on_single_reject']))
            || ! $this->isSafeExistingRingGroupMedia($data)
            || ! is_array($endpoints)
            || ! array_is_list($endpoints)
            || $endpoints === []
            || count($endpoints) > 20) {
            throw new InvalidArgumentException('The existing Ring Group configuration is not supported.');
        }

        $ids = [];
        $timings = [];

        foreach ($endpoints as $endpoint) {
            $delay = is_array($endpoint) && is_int($endpoint['delay'] ?? null) ? $endpoint['delay'] : 0;
            $timeout = is_array($endpoint) && is_int($endpoint['timeout'] ?? null) ? $endpoint['timeout'] : 20;

            if (! is_array($endpoint)
                || ! in_array($endpoint['endpoint_type'] ?? null, ['device', 'user', 'group'], true)
                || ! is_string($endpoint['id'] ?? null)
                || $endpoint['id'] === ''
                || strlen($endpoint['id']) > 128
                || $delay < 0
                || $delay > 60
                || $timeout < 1
                || $timeout > 60
                || (in_array($strategy, ['single', 'weighted_random'], true) && $delay !== 0)
                || ! $this->optionalIntegerInRange($endpoint, 'weight', 1, 100)
                || ($strategy === 'weighted_random'
                    && ! array_key_exists('weight', $endpoint))
                || in_array($endpoint['endpoint_type'].':'.$endpoint['id'], $ids, true)) {
                throw new InvalidArgumentException('The existing Ring Group configuration is not supported.');
            }

            $ids[] = $endpoint['endpoint_type'].':'.$endpoint['id'];
            $timings[] = ['delay' => $delay, 'timeout' => $timeout];
        }

        $attemptTimeout = $this->ringGroupAttemptTimeout($strategy, $timings);
        $storedTimeout = is_int($data['timeout'] ?? null) ? $data['timeout'] : 20;

        if ($attemptTimeout > 120 || $storedTimeout !== $attemptTimeout) {
            throw new InvalidArgumentException('The existing Ring Group configuration is not supported.');
        }
    }

    private function assertRingGroupMedia(): void
    {
        $ringback = $this->settings['ringback'] ?? null;

        if ($ringback !== null
            && (! is_string($ringback)
                || preg_match('/^[A-Za-z0-9_-]{1,128}$/', $ringback) !== 1)) {
            throw new InvalidArgumentException('The inline Ring Group ringback media is invalid.');
        }

        $ringtones = $this->settings['ringtones'] ?? null;

        if (! is_array($ringtones) || array_keys($ringtones) !== ['internal', 'external']) {
            throw new InvalidArgumentException('The inline Ring Group ringtone settings are invalid.');
        }

        foreach ($ringtones as $ringtone) {
            if ($ringtone !== null && ! $this->isSafeRingtone($ringtone)) {
                throw new InvalidArgumentException('The inline Ring Group ringtone settings are invalid.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function isSafeExistingRingGroupMedia(array $data): bool
    {
        $ringback = $data['ringback'] ?? null;

        if ($ringback !== null
            && $ringback !== ''
            && (! is_string($ringback)
                || preg_match('/^[A-Za-z0-9_-]{1,128}$/', $ringback) !== 1)) {
            return false;
        }

        if (! array_key_exists('ringtones', $data)) {
            return true;
        }

        $ringtones = $data['ringtones'];

        if (! is_array($ringtones)) {
            return false;
        }

        foreach (['internal', 'external'] as $key) {
            if (array_key_exists($key, $ringtones)
                && $ringtones[$key] !== ''
                && ! $this->isSafeRingtone($ringtones[$key])) {
                return false;
            }
        }

        return true;
    }

    private function isSafeRingtone(mixed $value): bool
    {
        return is_string($value)
            && $value !== ''
            && strlen($value) <= 256
            && preg_match('/[\x00\r\n]/', $value) !== 1;
    }

    /** @param list<array{delay: int, timeout: int}> $endpoints */
    private function ringGroupAttemptTimeout(string $strategy, array $endpoints): int
    {
        if (in_array($strategy, ['single', 'weighted_random'], true)) {
            return array_sum(array_column($endpoints, 'timeout'));
        }

        return max(array_map(
            fn (array $endpoint): int => $endpoint['delay'] + $endpoint['timeout'],
            $endpoints,
        ));
    }

    /** @param array<string, mixed> $data */
    private function optionalIntegerInRange(array $data, string $key, int $minimum, int $maximum): bool
    {
        if (! array_key_exists($key, $data)) {
            return true;
        }

        return is_int($data[$key]) && $data[$key] >= $minimum && $data[$key] <= $maximum;
    }

    private function assertReceiveFax(): void
    {
        $this->string('owner_id', 1, 128);
        $media = $this->settings['media'] ?? null;

        if (! is_array($media)
            || array_keys($media) !== ['fax_option']
            || (! is_bool($media['fax_option'] ?? null) && ($media['fax_option'] ?? null) !== 'auto')) {
            throw new InvalidArgumentException('The inline Receive Fax media setting is invalid.');
        }
    }

    private function assertBranchVariable(): void
    {
        if (($this->settings['variable'] ?? null) !== 'call_priority'
            || ($this->settings['scope'] ?? null) !== 'custom_channel_vars') {
            throw new InvalidArgumentException('The inline action branch variable setting is not supported.');
        }
    }

    private function assertBranchBnumber(): void
    {
        $this->boolean('hunt');
        $this->nullableString('hunt_allow', 512);
        $this->nullableString('hunt_deny', 512);

        foreach (['hunt_allow', 'hunt_deny'] as $key) {
            $value = $this->settings[$key] ?? null;

            if ($value !== null && ($value === '' || ! $this->safeRegex($value))) {
                throw new InvalidArgumentException(sprintf('The inline action %s setting is invalid.', $key));
            }
        }

        if (($this->settings['hunt'] ?? false) !== true
            && (($this->settings['hunt_allow'] ?? null) !== null
                || ($this->settings['hunt_deny'] ?? null) !== null)) {
            throw new InvalidArgumentException('Captured-number hunt filters require hunt mode.');
        }
    }

    private function assertMissedCallAlert(): void
    {
        $recipients = $this->settings['recipients'] ?? null;

        if (! is_array($recipients) || $recipients === [] || count($recipients) > 50) {
            throw new InvalidArgumentException('The inline action recipients setting is invalid.');
        }

        foreach ($recipients as $recipient) {
            if (! is_array($recipient) || array_diff(array_keys($recipient), ['type', 'id']) !== []) {
                throw new InvalidArgumentException('The inline action recipient is invalid.');
            }

            $type = $recipient['type'] ?? null;
            $id = $recipient['id'] ?? null;

            if (! is_string($type) || ! in_array($type, ['user', 'email'], true) || ! is_string($id)) {
                throw new InvalidArgumentException('The inline action recipient is invalid.');
            }

            if ($type === 'email') {
                if (strlen($id) > 254 || filter_var($id, FILTER_VALIDATE_EMAIL) === false) {
                    throw new InvalidArgumentException('The inline action email recipient is invalid.');
                }
            } elseif ($id === '' || strlen($id) > 128) {
                throw new InvalidArgumentException('The inline action user recipient is invalid.');
            }
        }
    }

    private function assertSetCid(): void
    {
        $this->string('caller_id_name', 0, 128);
        $this->string('caller_id_number', 0, 64);
    }

    private function assertPrependCid(): void
    {
        $this->oneOf('action', ['reset', 'prepend']);
        $this->oneOf('apply_to', ['original', 'current']);
        $this->string('caller_id_name_prefix', 0, 128);
        $this->string('caller_id_number_prefix', 0, 64);
    }

    private function assertTemporalRouteOperation(): void
    {
        $this->oneOf('action', ['disable', 'enable', 'reset']);
        $rules = $this->settings['rules'] ?? null;

        if (! is_array($rules) || count($rules) > 250) {
            throw new InvalidArgumentException('The inline temporal-rule selection is invalid.');
        }

        foreach ($rules as $rule) {
            if (! is_string($rule) || $rule === '' || strlen($rule) > 128) {
                throw new InvalidArgumentException('The inline temporal-rule selection is invalid.');
            }
        }
    }

    private function assertRingGroupToggle(): void
    {
        $this->oneOf('action', ['login', 'logout']);
        $this->string('callflow_id', 1, 128);
    }

    private function assertAcdcQueue(): void
    {
        $this->oneOf('action', ['login', 'logout']);
        $this->string('id', 1, 128);
    }

    private function assertSetAlertInfo(): void
    {
        $this->string('alert_info', 1, 256);

        if (str_contains($this->settings['alert_info'], "\r") || str_contains($this->settings['alert_info'], "\n")) {
            throw new InvalidArgumentException('The inline action alert_info setting is invalid.');
        }
    }

    private function assertCheckCid(): void
    {
        $this->string('regex', 1, 512);

        if (! $this->safeRegex($this->settings['regex'])
            || ($this->settings['use_absolute_mode'] ?? null) !== false) {
            throw new InvalidArgumentException('The inline action caller ID check mode is invalid.');
        }

        $callerId = $this->settings['caller_id'] ?? null;
        $userId = $this->settings['user_id'] ?? null;

        if ($callerId === null && $userId === null) {
            return;
        }

        if (! is_array($callerId)
            || array_diff(array_keys($callerId), ['external']) !== []
            || ! is_array($callerId['external'] ?? null)
            || array_diff(array_keys($callerId['external']), ['name', 'number']) !== []
            || ! is_string($userId)
            || $userId === '') {
            throw new InvalidArgumentException('The inline action caller identity override is invalid.');
        }

        $external = $callerId['external'];
        $name = $external['name'] ?? null;
        $number = $external['number'] ?? null;

        if (! is_string($name) || $name === '' || strlen($name) > 128
            || ! is_string($number) || $number === '' || strlen($number) > 64
            || strlen($userId) > 128) {
            throw new InvalidArgumentException('The inline action caller identity override is invalid.');
        }
    }

    private function safeRegex(mixed $value): bool
    {
        return is_string($value)
            && ! str_contains($value, "\x1F")
            && preg_match('/\(\?(?:R|0|&|P>|\{|\?)/', $value) !== 1
            && ! str_contains($value, '(*')
            && @preg_match("\x1F{$value}\x1Fu", '') !== false;
    }

    private function integer(string $key, int $minimum, int $maximum): void
    {
        $value = $this->settings[$key] ?? null;

        if (! is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException(sprintf('The inline action %s setting is invalid.', $key));
        }
    }

    private function nullableInteger(string $key, int $minimum, int $maximum): void
    {
        if (($this->settings[$key] ?? null) === null) {
            return;
        }

        $this->integer($key, $minimum, $maximum);
    }

    private function boolean(string $key): void
    {
        if (! is_bool($this->settings[$key] ?? null)) {
            throw new InvalidArgumentException(sprintf('The inline action %s setting must be boolean.', $key));
        }
    }

    /** @param list<string> $values */
    private function oneOf(string $key, array $values): void
    {
        if (! is_string($this->settings[$key] ?? null) || ! in_array($this->settings[$key], $values, true)) {
            throw new InvalidArgumentException(sprintf('The inline action %s setting is invalid.', $key));
        }
    }

    /** @param list<string> $values */
    private function nullableOneOf(string $key, array $values): void
    {
        if (($this->settings[$key] ?? null) === null) {
            return;
        }

        $this->oneOf($key, $values);
    }

    private function string(string $key, int $minimum, int $maximum): void
    {
        $value = $this->settings[$key] ?? null;
        $length = is_string($value)
            ? (function_exists('mb_strlen') ? mb_strlen($value) : strlen($value))
            : null;

        if (! is_int($length) || $length < $minimum || $length > $maximum) {
            throw new InvalidArgumentException(sprintf('The inline action %s setting is invalid.', $key));
        }
    }

    private function nullableString(string $key, int $maximum): void
    {
        if (($this->settings[$key] ?? null) === null) {
            return;
        }

        $this->string($key, 0, $maximum);
    }

    private function dtmfList(string $key): void
    {
        $value = $this->settings[$key] ?? null;
        $allowed = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '#', '*'];

        if (! is_array($value) || count($value) > count($allowed) || count($value) !== count(array_unique($value))) {
            throw new InvalidArgumentException(sprintf('The inline action %s setting is invalid.', $key));
        }

        foreach ($value as $digit) {
            if (! is_string($digit) || ! in_array($digit, $allowed, true)) {
                throw new InvalidArgumentException(sprintf('The inline action %s setting is invalid.', $key));
            }
        }
    }

    /** @param list<string> $path */
    private function assertPublicPath(array $path): void
    {
        foreach ($path as $segment) {
            if (! is_string($segment) || ! CallflowBranchPolicy::isPublicKey($segment)) {
                throw new InvalidArgumentException('The callflow path contains a preserved or unsupported branch.');
            }
        }
    }

    /** @param array<string, mixed> $node @param list<string> $path @return array<string, mixed> */
    private function nodeAt(array $node, array $path, string $name): array
    {
        foreach ($path as $segment) {
            if (! is_string($segment) || ! CallflowBranchPolicy::supports($node, $segment)) {
                throw new InvalidArgumentException(sprintf('The callflow %s path contains a preserved branch.', $name));
            }

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $child = $children[$segment] ?? null;

            if (! is_array($child)) {
                throw new InvalidArgumentException(sprintf('The callflow %s path no longer exists.', $name));
            }

            $node = $child;
        }

        return $node;
    }

    /** @param array<string, mixed> $node @param list<string> $parentPath */
    private function insertAt(
        array &$node,
        array $parentPath,
        string $branch,
        string $placement,
    ): void {
        if ($parentPath === []) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $existing = is_array($children[$branch] ?? null) ? $children[$branch] : null;
            $newChildren = $placement === 'insert_before' && $existing !== null
                ? ['_' => $existing]
                : [];
            $children[$branch] = [
                'module' => $this->module,
                'data' => $this->settingsForWrite([]),
                'children' => $newChildren === [] ? (object) [] : $newChildren,
            ];
            $node['children'] = $children;

            return;
        }

        $segment = array_shift($parentPath);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $child = is_string($segment) ? ($children[$segment] ?? null) : null;

        if (! is_array($child) || ! is_string($segment)) {
            throw new InvalidArgumentException('The destination callflow path no longer exists.');
        }

        $this->insertAt($child, $parentPath, $branch, $placement);
        $children[$segment] = $child;
        $node['children'] = $children;
    }

    /** @param array<string, mixed> $node @param list<string> $path */
    private function updateAt(array &$node, array $path): void
    {
        if ($path === []) {
            $current = is_array($node['data'] ?? null) ? $node['data'] : [];
            $node['data'] = $this->settingsForWrite($current);

            return;
        }

        $segment = array_shift($path);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $child = is_string($segment) ? ($children[$segment] ?? null) : null;

        if (! is_array($child) || ! is_string($segment)) {
            throw new InvalidArgumentException('The selected callflow path no longer exists.');
        }

        if ($path === []) {
            $current = is_array($child['data'] ?? null) ? $child['data'] : [];
            $child['data'] = $this->settingsForWrite($current);
            $children[$segment] = $child;
            $node['children'] = $children;

            return;
        }

        $this->updateAt($child, $path);
        $children[$segment] = $child;
        $node['children'] = $children;
    }

    /** @param array<string, mixed> $current @return array<string, mixed> */
    private function settingsForWrite(array $current): array
    {
        foreach (self::MANAGED_KEYS[$this->module] as $key) {
            if (! array_key_exists($key, $this->settings) || $this->settings[$key] === null) {
                unset($current[$key]);
            } elseif ($this->module === 'receive_fax' && $key === 'media') {
                $currentMedia = is_array($current['media'] ?? null) ? $current['media'] : [];
                $currentMedia['fax_option'] = $this->settings['media']['fax_option'];
                $current['media'] = $currentMedia;
            } elseif ($this->module === 'ring_group' && $key === 'ringtones') {
                $currentRingtones = is_array($current['ringtones'] ?? null)
                    ? $current['ringtones']
                    : [];

                foreach (['internal', 'external'] as $ringtone) {
                    if ($this->settings['ringtones'][$ringtone] === null) {
                        unset($currentRingtones[$ringtone]);
                    } else {
                        $currentRingtones[$ringtone] = $this->settings['ringtones'][$ringtone];
                    }
                }

                if ($currentRingtones === []) {
                    unset($current['ringtones']);
                } else {
                    $current['ringtones'] = $currentRingtones;
                }
            } elseif (in_array($this->module, ['page_group', 'ring_group'], true) && $key === 'endpoints') {
                $current['endpoints'] = $this->deviceEndpointsForWrite($current['endpoints'] ?? null);
            } else {
                $current[$key] = $this->module === 'set_variables'
                    && $key === 'custom_application_vars'
                        ? (object) $this->settings[$key]
                        : $this->settings[$key];
            }
        }

        return $current;
    }

    /** @return list<array<string, mixed>> */
    private function deviceEndpointsForWrite(mixed $current): array
    {
        $existing = [];

        foreach (is_array($current) ? $current : [] as $endpoint) {
            if (is_array($endpoint)
                && is_string($endpoint['endpoint_type'] ?? null)
                && is_string($endpoint['id'] ?? null)) {
                $existing[$endpoint['endpoint_type']."\0".$endpoint['id']] = $endpoint;
            }
        }

        return array_map(function (array $endpoint) use ($existing): array {
            $key = $endpoint['endpoint_type']."\0".$endpoint['id'];

            return [...($existing[$key] ?? []), ...$endpoint];
        }, $this->settings['endpoints']);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function withoutPrivateDocumentFields(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if (in_array($key, ['id', '_id', '_rev', 'account_id', 'created', 'modified'], true)
                || str_starts_with($key, 'pvt_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $node @return array<string, mixed> */
    private function normalizeNodeForJson(array $node): array
    {
        $children = [];

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $key => $child) {
            if ((is_string($key) || is_int($key)) && is_array($child)) {
                $children[(string) $key] = $this->normalizeNodeForJson($child);
            }
        }

        $node['children'] = (object) $children;

        return $node;
    }
}
