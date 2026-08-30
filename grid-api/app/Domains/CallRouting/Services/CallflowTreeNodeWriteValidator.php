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
        'response',
        'hangup',
        'set_variable',
        'set_variables',
        'manual_presence',
        'group_pickup',
        'page_group',
        'ring_group',
        'receive_fax',
        'branch_variable',
        'branch_bnumber',
        'missed_call_alert',
        'set_cid',
        'prepend_cid',
        'set_alert_info',
        'check_cid',
        'cidlistmatch',
        'ring_group_toggle',
        'hotdesk',
        'do_not_disturb',
        'call_forward',
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

        if (CallflowBranchPolicy::childrenAreLocked($parent)) {
            $this->fail('parent_path', 'This conditional action has preserved branches that cannot be edited.');
        }

        if (! in_array($parentModule, self::GUIDED_MODULES, true)) {
            $this->fail('parent_path', 'New actions cannot be attached to this unsupported callflow module.');
        }

        if (($parent['reference_status'] ?? null) === 'unresolved') {
            $this->fail('parent_path', 'Resolve the parent callflow action before adding a child action.');
        }

        if (! in_array($module, self::GUIDED_MODULES, true)) {
            $this->fail('destination_type', 'This callflow action is not available in the guided editor.');
        }

        if (! CallflowBranchPolicy::supports($parent, $branch)) {
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
        ?array $settings = null,
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

        if ($currentModule === 'check_cid') {
            $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];

            if (($settings['use_absolute_mode'] ?? false) === true) {
                $this->fail('node_path', 'Absolute-mode caller ID checks are preserved but cannot be edited.');
            }

            if (($settings['identity_reference_status'] ?? 'not_applicable') === 'unresolved') {
                $this->fail('node_path', 'Synchronize the caller identity owner before editing this check.');
            }
        }

        if ($currentModule === 'cidlistmatch'
            && ($node['settings']['reference_status'] ?? 'unresolved') === 'unresolved') {
            $this->fail('node_path', 'Synchronize the Caller-ID List before editing this match action.');
        }

        if ($currentModule === 'set_variable'
            && ($node['settings']['supported_variable'] ?? false) !== true) {
            $this->fail('node_path', 'This existing channel variable is not supported by the guided editor.');
        }

        if ($currentModule === 'set_variables'
            && ($node['settings']['supported_variables'] ?? false) !== true) {
            $this->fail('node_path', 'This existing custom application variable set is not supported by the guided editor.');
        }

        if ($currentModule === 'group_pickup'
            && ($node['settings']['supported_target'] ?? false) !== true) {
            $this->fail('node_path', 'This existing Group Pickup target is not supported by the guided editor.');
        }

        if ($currentModule === 'page_group'
            && ($node['settings']['supported_configuration'] ?? false) !== true) {
            $this->fail('node_path', 'This existing Page Group configuration is not supported by the guided editor.');
        }

        if ($currentModule === 'ring_group'
            && ($node['settings']['supported_configuration'] ?? false) !== true) {
            $this->fail('node_path', 'This existing Ring Group configuration is not supported by the guided editor.');
        }

        if ($currentModule === 'receive_fax'
            && ($node['settings']['supported_configuration'] ?? false) !== true) {
            $this->fail('node_path', 'This existing Receive Fax configuration is not supported by the guided editor.');
        }

        if ($currentModule === 'branch_variable'
            && ($node['settings']['supported_variable'] ?? false) !== true) {
            $this->fail('node_path', 'This existing branch variable is not supported by the guided editor.');
        }

        if ($currentModule === 'branch_bnumber' && ($settings['hunt'] ?? false) === true) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $exactBranches = array_filter(array_keys($children), fn (string|int $key): bool => (string) $key !== '_');

            if ($exactBranches !== []) {
                $this->fail('data.hunt', 'Remove exact captured-number branches before enabling hunt mode.');
            }
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
            if (! CallflowBranchPolicy::supports($node, $segment)) {
                $this->fail($field, 'The selected callflow path contains a preserved branch.');
            }

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $child = $children[$segment] ?? null;

            if (! is_array($child)) {
                $this->fail($field, 'The selected callflow path no longer exists.');
            }

            $node = $child;
        }

        return $node;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
