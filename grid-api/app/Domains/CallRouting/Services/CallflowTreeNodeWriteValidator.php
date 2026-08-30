<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Models\SwitchCallflow;
use Illuminate\Validation\ValidationException;

class CallflowTreeNodeWriteValidator
{
    /** @var list<string> */
    private const GUIDED_MODULES = [
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
        'sleep',
        'tts',
        'collect_dtmf',
        'record_call',
        'record_caller',
        'send_dtmf',
        'flush_dtmf',
        'dead_air',
        'language',
        'missed_call_alert',
    ];

    /** @param list<string> $parentPath */
    public function assertCanCreate(
        SwitchCallflow $callflow,
        array $parentPath,
        string $branch,
        string $module,
    ): void {
        $flow = $this->flow($callflow);
        $parent = $this->nodeAt($flow, $parentPath, 'parent_path');
        $parentModule = is_string($parent['module'] ?? null) ? $parent['module'] : '';

        if (! in_array($parentModule, self::GUIDED_MODULES, true)) {
            $this->fail('parent_path', 'New actions cannot be attached to this unsupported callflow module.');
        }

        if (($parent['reference_status'] ?? null) === 'unresolved') {
            $this->fail('parent_path', 'Resolve the parent callflow action before adding a child action.');
        }

        if (! in_array($module, self::GUIDED_MODULES, true)) {
            $this->fail('destination_type', 'This callflow action is not available in the guided editor.');
        }

        if (! $this->supportsBranch($parentModule, $branch)) {
            $this->fail('branch', 'The selected branch is not valid for the parent callflow action.');
        }

        $children = is_array($parent['children'] ?? null) ? $parent['children'] : [];

        if (array_key_exists($branch, $children)) {
            $this->fail('branch', 'The selected callflow branch is already occupied.');
        }
    }

    /** @param list<string> $nodePath */
    public function assertCanUpdate(
        SwitchCallflow $callflow,
        array $nodePath,
        string $module,
    ): void {
        if ($nodePath === []) {
            $this->fail('node_path', 'Edit the root action through the guided route editor.');
        }

        $node = $this->nodeAt($this->flow($callflow), $nodePath, 'node_path');
        $currentModule = is_string($node['module'] ?? null) ? $node['module'] : '';

        if (! in_array($currentModule, self::GUIDED_MODULES, true)) {
            $this->fail('node_path', 'This callflow action is not supported by the guided editor.');
        }

        if ($currentModule !== $module) {
            $this->fail('destination_type', 'The selected destination does not match the callflow action module.');
        }
    }

    /** @return array<string, mixed> */
    private function flow(SwitchCallflow $callflow): array
    {
        if (! is_array($callflow->flow_structure)) {
            $this->fail('callflow', 'This route has no flow tree to edit.');
        }

        return $callflow->flow_structure;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $path
     * @return array<string, mixed>
     */
    private function nodeAt(array $node, array $path, string $field): array
    {
        foreach ($path as $segment) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $child = $children[$segment] ?? null;

            if (! is_array($child)) {
                $this->fail($field, 'The selected callflow path no longer exists.');
            }

            $node = $child;
        }

        return $node;
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

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
