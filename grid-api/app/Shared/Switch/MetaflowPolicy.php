<?php

namespace App\Shared\Switch;

use App\Domains\Organizations\Models\SwitchAccount;
use InvalidArgumentException;

class MetaflowPolicy
{
    /** @var array<string, array<string, array<string, string>>> */
    private array $resourceCache = [];

    /** @var array<string, list<string>> */
    public const EDITABLE_MODULE_FIELDS = [
        'audio_level' => ['action', 'level', 'mode'],
        'break' => [],
        'callflow' => ['callflow_id', 'captures', 'collected'],
        'hangup' => [],
        'hold_control' => ['action'],
        'move' => ['device_id', 'extension_id', 'auto_answer', 'can_call_self', 'dial_strategy'],
        'play' => ['media_id', 'leg'],
        'record_call' => ['action', 'dtmf_leg', 'format', 'label', 'media_name', 'origin', 'record_min_sec', 'record_on_answer', 'record_on_bridge', 'record_sample_rate', 'time_limit'],
        'resume' => [],
        'say' => ['gender', 'language', 'method', 'text', 'type'],
        'sound_touch' => ['action', 'adjust_in_octaves', 'adjust_in_semitones', 'hook_dtmf', 'pitch', 'rate', 'sending_leg', 'tempo'],
        'transfer' => ['leg', 'target', 'transfer_type'],
        'tts' => ['engine', 'language', 'leg', 'text', 'voice'],
    ];

    /** @return list<array<string, mixed>> */
    public function editableActions(mixed $metaflows, ?SwitchAccount $account = null): array
    {
        if (! is_array($metaflows)) {
            return [];
        }

        $resources = $this->resources($account);
        $actions = [];

        foreach (['numbers' => 'number', 'patterns' => 'pattern'] as $map => $triggerType) {
            foreach (($metaflows[$map] ?? []) as $trigger => $action) {
                if (! is_string($trigger) && ! is_int($trigger)) {
                    continue;
                }

                $node = $this->decodeNode($action, $resources);

                if ($node !== null) {
                    $actions[] = ['trigger_type' => $triggerType, 'trigger' => (string) $trigger] + $node;
                }
            }
        }

        return $actions;
    }

    public function lockedActionCount(mixed $metaflows, ?SwitchAccount $account = null): int
    {
        if (! is_array($metaflows)) {
            return 0;
        }

        $resources = $this->resources($account);
        $count = 0;

        foreach (['numbers', 'patterns'] as $map) {
            foreach (($metaflows[$map] ?? []) as $action) {
                if ($this->decodeNode($action, $resources) === null) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Replace only editable trees and retain unsupported or unresolved trees verbatim.
     *
     * @param  list<array<string, mixed>>  $submitted
     * @return array{numbers: array<string, mixed>, patterns: array<string, mixed>}
     */
    public function merge(mixed $currentMetaflows, array $submitted, ?SwitchAccount $account = null): array
    {
        $current = is_array($currentMetaflows) ? $currentMetaflows : [];
        $resources = $this->resources($account);
        $maps = [];

        foreach (['numbers', 'patterns'] as $map) {
            $maps[$map] = array_filter(
                is_array($current[$map] ?? null) ? $current[$map] : [],
                fn (mixed $action): bool => $this->decodeNode($action, $resources) === null,
            );
        }

        foreach ($submitted as $action) {
            $map = ($action['trigger_type'] ?? null) === 'pattern' ? 'patterns' : 'numbers';
            $maps[$map][(string) $action['trigger']] = $this->encodeNode($action, $resources);
        }

        return $maps;
    }

    public function isEditableAction(mixed $action): bool
    {
        return $this->decodeNode($action, $this->resources(null)) !== null;
    }

    /**
     * @param  array<string, array<string, string>>  $resources
     * @return array{module: string, data: array<string, mixed>, children: list<array<string, mixed>>}|null
     */
    private function decodeNode(mixed $node, array $resources): ?array
    {
        if (! is_array($node) || ! is_string($node['module'] ?? null)) {
            return null;
        }

        $module = $node['module'];
        $data = $this->decodeData($module, $node['data'] ?? [], $resources);

        if ($data === null || ! is_array($node['children'] ?? [])) {
            return null;
        }

        $children = [];

        foreach (($node['children'] ?? []) as $key => $child) {
            if (! is_string($key) && ! is_int($key)) {
                return null;
            }

            $decoded = $this->decodeNode($child, $resources);

            if ($decoded === null) {
                return null;
            }

            $children[] = ['key' => (string) $key] + $decoded;
        }

        return ['module' => $module, 'data' => $data, 'children' => $children];
    }

    /** @param array<string, array<string, string>> $resources @return array<string, mixed>|null */
    private function decodeData(string $module, mixed $data, array $resources): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $translated = $data;

        foreach ($this->resourceFields($module) as $switchField => [$publicField, $resource]) {
            if (! array_key_exists($switchField, $translated)) {
                continue;
            }

            $switchId = $translated[$switchField];
            $publicId = is_string($switchId) ? ($resources["{$resource}_to_public"][$switchId] ?? null) : null;

            if ($publicId === null) {
                return null;
            }

            unset($translated[$switchField]);
            $translated[$publicField] = $publicId;
        }

        $allowedFields = self::EDITABLE_MODULE_FIELDS[$module] ?? null;

        if ($allowedFields === null) {
            return null;
        }

        foreach ($translated as $field => $value) {
            if (! is_string($field) || ! in_array($field, $allowedFields, true) || ! $this->safeValue($value)) {
                return null;
            }
        }

        return $translated;
    }

    /** @param array<string, mixed> $node @param array<string, array<string, string>> $resources @return array<string, mixed> */
    private function encodeNode(array $node, array $resources): array
    {
        $module = (string) $node['module'];
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        foreach ($this->resourceFields($module) as $switchField => [$publicField, $resource]) {
            if (! array_key_exists($publicField, $data)) {
                continue;
            }

            $publicId = $data[$publicField];
            unset($data[$publicField]);
            $switchId = is_string($publicId)
                ? ($resources["{$resource}_to_switch"][$publicId] ?? null)
                : null;

            if ($switchId === null) {
                throw new InvalidArgumentException('Metaflow resource is not projected for this account.');
            }

            $data[$switchField] = $switchId;
        }

        $children = [];

        foreach (($node['children'] ?? []) as $child) {
            if (is_array($child) && is_string($child['key'] ?? null)) {
                $children[$child['key']] = $this->encodeNode($child, $resources);
            }
        }

        return [
            'module' => $module,
            'data' => $data,
            'children' => (object) $children,
        ];
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function resourceFields(string $module): array
    {
        return match ($module) {
            'play' => ['id' => ['media_id', 'media']],
            'callflow' => ['id' => ['callflow_id', 'callflow']],
            'move' => [
                'device_id' => ['device_id', 'device'],
                'owner_id' => ['extension_id', 'extension'],
            ],
            default => [],
        };
    }

    /** @return array<string, array<string, string>> */
    private function resources(?SwitchAccount $account): array
    {
        $resources = [];

        foreach (['media', 'callflow', 'device', 'extension'] as $resource) {
            $resources["{$resource}_to_public"] = [];
            $resources["{$resource}_to_switch"] = [];
        }

        if ($account === null || ! $account->exists) {
            return $resources;
        }

        $cacheKey = (string) $account->getKey();

        if (isset($this->resourceCache[$cacheKey])) {
            return $this->resourceCache[$cacheKey];
        }

        foreach ([
            'media' => $account->media(),
            'callflow' => $account->callflows(),
            'device' => $account->devices(),
            'extension' => $account->extensions(),
        ] as $resource => $query) {
            foreach ($query->get(['id', 'switch_resource_id']) as $model) {
                if (is_string($model->switch_resource_id) && $model->switch_resource_id !== '') {
                    $resources["{$resource}_to_public"][$model->switch_resource_id] = $model->id;
                    $resources["{$resource}_to_switch"][$model->id] = $model->switch_resource_id;
                }
            }
        }

        return $this->resourceCache[$cacheKey] = $resources;
    }

    private function safeValue(mixed $value): bool
    {
        return $value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value);
    }
}
