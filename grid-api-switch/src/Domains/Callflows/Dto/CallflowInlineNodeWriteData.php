<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use InvalidArgumentException;

/**
 * Adds or updates one schema-backed inline action while preserving unknown data and its subtree.
 */
final readonly class CallflowInlineNodeWriteData
{
    private const PUBLIC_BRANCH_KEYS = [
        '_', 'timeout', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*', 'rule_set',
    ];

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
        'missed_call_alert' => ['recipients', 'skip_module'],
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
        public string $module,
        private array $settings,
    ) {
        if (! is_array($this->current['flow'] ?? null)) {
            throw new InvalidArgumentException('Switch callflow must contain a root flow node before its tree can be edited.');
        }

        if (! array_key_exists($this->module, self::MANAGED_KEYS)) {
            throw new InvalidArgumentException('The inline Switch callflow action is not supported.');
        }

        $this->assertPublicPath($this->path);
        $this->assertSettings();

        /** @var array<string, mixed> $flow */
        $flow = $this->current['flow'];

        if ($this->operation === 'create') {
            if ($this->branch === null || ! in_array($this->branch, self::PUBLIC_BRANCH_KEYS, true)) {
                throw new InvalidArgumentException('The destination branch is not editable.');
            }
            $parent = $this->nodeAt($flow, $this->path, 'parent');
            $parentModule = is_string($parent['module'] ?? null) ? $parent['module'] : '';

            if (! $this->supportsBranch($parentModule, $this->branch)) {
                throw new InvalidArgumentException('The destination branch is not valid for the selected callflow node.');
            }

            $children = is_array($parent['children'] ?? null) ? $parent['children'] : [];

            if (array_key_exists($this->branch, $children)) {
                throw new InvalidArgumentException('The destination callflow branch is already occupied.');
            }

            return;
        }

        if ($this->path === []) {
            throw new InvalidArgumentException('The root callflow action must be edited through the route editor.');
        }
        $node = $this->nodeAt($flow, $this->path, 'node');

        if (($node['module'] ?? null) !== $this->module) {
            throw new InvalidArgumentException('The selected inline action module changed and must be reloaded.');
        }
    }

    /** @param array<string, mixed> $current @param list<string> $parentPath @param array<string, mixed> $settings */
    public static function create(
        array $current,
        array $parentPath,
        string $branch,
        string $module,
        array $settings,
    ): self {
        return new self($current, 'create', $parentPath, $branch, $module, $settings);
    }

    /** @param array<string, mixed> $current @param list<string> $nodePath @param array<string, mixed> $settings */
    public static function update(
        array $current,
        array $nodePath,
        string $module,
        array $settings,
    ): self {
        return new self($current, 'update', $nodePath, null, $module, $settings);
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = $this->withoutPrivateDocumentFields($this->current);
        /** @var array<string, mixed> $flow */
        $flow = $data['flow'];

        if ($this->operation === 'create' && $this->branch !== null) {
            $this->insertAt($flow, $this->path, $this->branch);
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
            'missed_call_alert' => $this->assertMissedCallAlert(),
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
            if (! is_string($segment) || ! in_array($segment, self::PUBLIC_BRANCH_KEYS, true)) {
                throw new InvalidArgumentException('The callflow path contains a preserved or unsupported branch.');
            }
        }
    }

    private function supportsBranch(string $module, string $branch): bool
    {
        if ($branch === '_') {
            return true;
        }

        if ($module === 'menu') {
            return in_array($branch, ['timeout', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*'], true);
        }

        return $module === 'temporal_route' && $branch === 'rule_set';
    }

    /** @param array<string, mixed> $node @param list<string> $path @return array<string, mixed> */
    private function nodeAt(array $node, array $path, string $name): array
    {
        foreach ($path as $segment) {
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
    private function insertAt(array &$node, array $parentPath, string $branch): void
    {
        if ($parentPath === []) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $children[$branch] = [
                'module' => $this->module,
                'data' => $this->settingsForWrite([]),
                'children' => (object) [],
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

        $this->insertAt($child, $parentPath, $branch);
        $children[$segment] = $child;
        $node['children'] = $children;
    }

    /** @param array<string, mixed> $node @param list<string> $path */
    private function updateAt(array &$node, array $path): void
    {
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
            } else {
                $current[$key] = $this->settings[$key];
            }
        }

        return $current;
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
