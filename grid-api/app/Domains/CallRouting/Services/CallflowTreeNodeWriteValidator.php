<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Models\SwitchCallflow;
use Illuminate\Validation\ValidationException;

class CallflowTreeNodeWriteValidator
{
    /** @param list<string> $parentPath */
    public function assertCanCreate(
        SwitchCallflow $callflow,
        array $parentPath,
        string $branch,
        string $module,
        string $placement = 'append',
        bool $confirmReplace = false,
    ): void {
        $flow = $this->flow($callflow);
        $parent = $this->nodeAt($flow, $parentPath, 'parent_path');
        $parentModule = is_string($parent['module'] ?? null) ? $parent['module'] : '';

        if (CallflowBranchPolicy::childrenAreLocked($parent)) {
            $this->fail('parent_path', 'This conditional action has preserved branches that cannot be edited.');
        }

        if (! CallflowBranchPolicy::isGuidedModule($parentModule)) {
            $this->fail('parent_path', 'New actions cannot be attached to this unsupported callflow module.');
        }

        if (($parent['reference_status'] ?? null) === 'unresolved') {
            $this->fail('parent_path', 'Resolve the parent callflow action before adding a child action.');
        }

        if (! CallflowBranchPolicy::isGuidedModule($module)) {
            $this->fail('destination_type', 'This callflow action is not available in the guided editor.');
        }

        if (! CallflowBranchPolicy::supports($parent, $branch)) {
            $this->fail('branch', 'The selected branch is not valid for the parent callflow action.');
        }

        $children = is_array($parent['children'] ?? null) ? $parent['children'] : [];

        $occupied = array_key_exists($branch, $children);

        if ($placement === 'append' && $occupied) {
            $this->fail('branch', 'The selected callflow branch is already occupied.');
        }

        if ($placement !== 'append' && ! $occupied) {
            $this->fail('placement', 'The selected callflow branch is no longer occupied. Reload the route.');
        }

        if ($placement !== 'append' && $branch !== '_') {
            $this->fail('placement', 'Only an occupied continuation branch can use this placement.');
        }

        if ($placement === 'insert_before' && CallflowBranchPolicy::isTerminalModule($module)) {
            $this->fail('placement', 'A terminal action cannot preserve the existing next step.');
        }

        if ($placement === 'replace' && ! $confirmReplace) {
            $this->fail('confirm_replace', 'Confirm replacement of the existing next step.');
        }
    }

    /** @param list<string> $nodePath */
    public function assertCanUpdate(
        SwitchCallflow $callflow,
        array $nodePath,
        string $module,
        ?array $settings = null,
    ): void {
        if ($nodePath === [] && ! in_array($module, ['ring_group', 'dynamic_cid'], true)) {
            $this->fail('node_path', 'Only a supported guided root action may be edited here.');
        }

        if ($nodePath === [] && $callflow->is_feature_code) {
            $this->fail('node_path', 'Feature-code route roots cannot be edited here.');
        }

        $node = $this->nodeAt($this->flow($callflow), $nodePath, 'node_path');
        $currentModule = is_string($node['module'] ?? null) ? $node['module'] : '';

        if (! CallflowBranchPolicy::isGuidedModule($currentModule)) {
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

        if ($currentModule === 'response'
            && ($node['settings']['media_reference_status'] ?? 'not_applicable') === 'unresolved') {
            $this->fail('node_path', 'Synchronize the response media before editing this action.');
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

        if ($currentModule === 'acdc_queue'
            && ($node['settings']['supported_configuration'] ?? false) !== true) {
            $this->fail('node_path', 'Synchronize the Queue before editing this ACDC Queue action.');
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

    /** @param list<string> $nodePath */
    public function assertCanDelete(SwitchCallflow $callflow, array $nodePath): void
    {
        if ($nodePath === []) {
            $this->fail('node_path', 'The root callflow action cannot be removed.');
        }

        $node = $this->nodeAt($this->flow($callflow), $nodePath, 'node_path');
        $module = is_string($node['module'] ?? null) ? $node['module'] : '';

        if (! CallflowBranchPolicy::isGuidedModule($module)) {
            $this->fail('node_path', 'This callflow action is preserved and cannot be removed here.');
        }

        if (($node['branch']['kind'] ?? null) === 'preserved') {
            $this->fail('node_path', 'This preserved callflow branch cannot be removed here.');
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
