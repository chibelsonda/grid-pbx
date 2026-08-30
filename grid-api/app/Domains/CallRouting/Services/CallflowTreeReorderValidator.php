<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Models\SwitchCallflow;
use Illuminate\Validation\ValidationException;

class CallflowTreeReorderValidator
{
    /** @var list<string> */
    private const GUIDED_MODULES = [
        'user', 'device', 'voicemail', 'callflow', 'play', 'directory', 'group',
        'acdc_member', 'menu', 'conference', 'faxbox', 'temporal_route',
        'sleep', 'tts', 'collect_dtmf', 'record_call', 'record_caller',
        'send_dtmf', 'flush_dtmf', 'dead_air', 'language', 'response', 'hangup', 'set_variable',
        'branch_variable',
        'missed_call_alert',
        'set_cid', 'prepend_cid', 'set_alert_info', 'check_cid', 'cidlistmatch',
        'ring_group_toggle', 'hotdesk', 'do_not_disturb', 'call_forward',
    ];

    /** @param list<string> $sourcePath @param list<string> $targetPath */
    public function assertAllowed(
        SwitchCallflow $callflow,
        string $mode,
        array $sourcePath,
        array $targetPath,
    ): void {
        if ($sourcePath === $targetPath) {
            $this->fail('target_path', 'Select a different callflow action to reorder.');
        }

        $flow = $callflow->flow_structure;

        if (! is_array($flow)) {
            $this->fail('callflow', 'This route has no flow tree to reorder.');
        }

        $source = $this->nodeAt($flow, $sourcePath, 'source_path');
        $target = $this->nodeAt($flow, $targetPath, 'target_path');

        foreach ([['source_path', $source], ['target_path', $target]] as [$field, $node]) {
            $module = is_string($node['module'] ?? null) ? $node['module'] : '';

            if (! in_array($module, self::GUIDED_MODULES, true)) {
                $this->fail($field, 'This callflow action is not supported by the guided reorder editor.');
            }
        }

        if (($source['reference_status'] ?? null) === 'unresolved') {
            $this->fail('source_path', 'An unresolved callflow action cannot be reordered safely.');
        }

        if ($this->pathStartsWith($targetPath, $sourcePath)) {
            $this->fail('target_path', 'A callflow action cannot be reordered into its own subtree.');
        }

        if ($mode === 'swap' && $this->pathStartsWith($sourcePath, $targetPath)) {
            $this->fail('target_path', 'Ancestor and descendant actions cannot be swapped.');
        }

        $sourceChildren = is_array($source['children'] ?? null) ? $source['children'] : [];

        if ($mode === 'insert_before' && array_key_exists('_', $sourceChildren)) {
            $this->fail(
                'source_path',
                'Insert-before requires the moving action to have an empty default continuation.',
            );
        }
    }

    /** @param array<string, mixed> $node @param list<string> $path @return array<string, mixed> */
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

    /** @param list<string> $path */
    private function pathStartsWith(array $path, array $prefix): bool
    {
        return count($path) >= count($prefix)
            && array_slice($path, 0, count($prefix)) === $prefix;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
