<?php

namespace App\Shared\Switch;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Validation\Rules\SafeSwitchRegex;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class MetaflowInputValidator
{
    /** @param list<mixed> $actions */
    public function validate(
        Validator $validator,
        array $actions,
        SwitchAccount $account,
        string $path = 'metaflows.actions',
    ): void {
        $seen = [];
        $nodeCount = 0;
        $resources = $this->resourceIds($account);

        foreach ($actions as $index => $action) {
            if (! is_array($action)) {
                continue;
            }

            $actionPath = "{$path}.{$index}";
            $triggerType = $action['trigger_type'] ?? null;
            $trigger = $action['trigger'] ?? null;
            $identity = (is_string($triggerType) ? $triggerType : '')
                .':'
                .(is_string($trigger) ? $trigger : '');

            if ($triggerType === 'number'
                && is_string($trigger)
                && preg_match('/^[0-9]+$/', $trigger) !== 1) {
                $validator->errors()->add("{$actionPath}.trigger", 'Number metaflow triggers may contain digits only.');
            }

            if ($triggerType === 'pattern'
                && is_string($trigger)
                && ! SafeSwitchRegex::isSafe($trigger)) {
                $validator->errors()->add("{$actionPath}.trigger", 'Enter a supported metaflow pattern.');
            }

            if (isset($seen[$identity])) {
                $validator->errors()->add("{$actionPath}.trigger", 'Each metaflow trigger must be unique within its type.');
            }
            $seen[$identity] = true;

            $this->validateNode($validator, $action, $actionPath, 0, $nodeCount, $resources);
        }

        if ($nodeCount > 100) {
            $validator->errors()->add($path, 'Use no more than 100 guided metaflow nodes.');
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<string, true>>  $resources
     */
    private function validateNode(
        Validator $validator,
        array $node,
        string $path,
        int $depth,
        int &$nodeCount,
        array $resources,
    ): void {
        $nodeCount++;

        if ($depth > 8) {
            $validator->errors()->add($path.'.children', 'Metaflow branches may be at most 8 levels deep.');

            return;
        }

        $module = is_string($node['module'] ?? null) ? $node['module'] : '';
        $allowed = MetaflowPolicy::EDITABLE_MODULE_FIELDS[$module] ?? [];

        foreach ((array) ($node['data'] ?? []) as $field => $value) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                $validator->errors()->add($path.'.data', 'The selected metaflow action contains an unsupported field.');

                continue;
            }

            if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
                $validator->errors()->add("{$path}.data.{$field}", 'Metaflow values must be text, numbers, booleans, or null.');
            }

            if (is_string($value) && strlen($value) > 2048) {
                $validator->errors()->add("{$path}.data.{$field}", 'Metaflow text values must not exceed 2048 characters.');
            }
        }

        foreach ($this->resourceFields($module) as $field => $resource) {
            if (! array_key_exists($field, (array) ($node['data'] ?? []))) {
                continue;
            }

            $id = $node['data'][$field];

            if (! is_string($id) || ! Str::isUuid($id) || ! isset($resources[$resource][$id])) {
                $validator->errors()->add(
                    "{$path}.data.{$field}",
                    'Select a projected resource from this account.',
                );
            }
        }

        if ($module === 'play' && ! isset($node['data']['media_id'])) {
            $validator->errors()->add($path.'.data.media_id', 'Select media to play.');
        }

        if ($module === 'callflow' && ! isset($node['data']['callflow_id'])) {
            $validator->errors()->add($path.'.data.callflow_id', 'Select a callflow to run.');
        }

        if ($module === 'move'
            && ! isset($node['data']['device_id'])
            && ! isset($node['data']['extension_id'])) {
            $validator->errors()->add($path.'.data', 'Select a destination device or extension.');
        }

        $seenKeys = [];

        foreach ((array) ($node['children'] ?? []) as $index => $child) {
            $childPath = "{$path}.children.{$index}";

            if (! is_array($child)) {
                $validator->errors()->add($childPath, 'Each metaflow child must be an object.');

                continue;
            }

            $key = $child['key'] ?? null;

            if (! is_string($key) || trim($key) === '' || strlen($key) > 64) {
                $validator->errors()->add($childPath.'.key', 'Enter a branch key up to 64 characters.');
            } elseif (isset($seenKeys[$key])) {
                $validator->errors()->add($childPath.'.key', 'Branch keys must be unique at this level.');
            } else {
                $seenKeys[$key] = true;
            }

            if (! is_string($child['module'] ?? null)
                || ! array_key_exists($child['module'], MetaflowPolicy::EDITABLE_MODULE_FIELDS)) {
                $validator->errors()->add($childPath.'.module', 'Select a supported metaflow action.');
            }

            $this->validateNode(
                $validator,
                $child,
                $childPath,
                $depth + 1,
                $nodeCount,
                $resources,
            );
        }
    }

    /** @return array<string, string> */
    private function resourceFields(string $module): array
    {
        return match ($module) {
            'play' => ['media_id' => 'media'],
            'callflow' => ['callflow_id' => 'callflow'],
            'move' => ['device_id' => 'device', 'extension_id' => 'extension'],
            default => [],
        };
    }

    /** @return array<string, array<string, true>> */
    private function resourceIds(SwitchAccount $account): array
    {
        $resources = [];

        foreach ([
            'media' => $account->media(),
            'callflow' => $account->callflows(),
            'device' => $account->devices(),
            'extension' => $account->extensions(),
        ] as $resource => $query) {
            $resources[$resource] = $query
                ->whereNotNull('switch_resource_id')
                ->where('switch_resource_id', '!=', '')
                ->pluck('id')
                ->mapWithKeys(static fn (string $id): array => [$id => true])
                ->all();
        }

        return $resources;
    }
}
