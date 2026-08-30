<?php

namespace App\Domains\CallRouting\Services;

final class CallflowBranchPolicy
{
    /** @var list<string> */
    private const LOCKED_MODULES = [
        'acdc_agent',
        'call_forward',
        'eavesdrop',
        'eavesdrop_feature',
    ];

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
        'acdc_queue',
        'hotdesk',
        'do_not_disturb',
    ];

    /**
     * Monster models these actions with a zero-child quantity rule. Hangup and
     * Dead Air are current-schema terminal actions with the same semantics.
     *
     * @var list<string>
     */
    private const TERMINAL_MODULES = [
        'dead_air',
        'disa',
        'group_pickup',
        'hangup',
        'offnet',
        'pivot',
        'receive_fax',
        'resources',
        'response',
    ];

    /** @var list<string> */
    private const FIXED_PUBLIC_KEYS = [
        '_', 'timeout', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*',
        'rule_set', 'match', 'nomatch',
    ];

    public static function isPublicKey(string $key): bool
    {
        return in_array($key, self::FIXED_PUBLIC_KEYS, true)
            || self::isPriorityKey($key)
            || self::isCapturedNumberKey($key);
    }

    /** @param array<string, mixed> $node */
    public static function supports(array $node, string $branch): bool
    {
        $module = is_string($node['module'] ?? null) ? $node['module'] : '';

        if (in_array($module, self::LOCKED_MODULES, true)) {
            return false;
        }

        if (self::isTerminalModule($module)) {
            return false;
        }

        if ($module === 'branch_variable') {
            return self::supportsCallPriority($node)
                && ($branch === '_' || self::isPriorityKey($branch));
        }

        if ($module === 'branch_bnumber') {
            $settings = self::settings($node);

            return $branch === '_'
                || (($settings['hunt'] ?? false) !== true && self::isCapturedNumberKey($branch));
        }

        if ($branch === '_') {
            return true;
        }

        if ($module === 'menu') {
            return in_array($branch, ['timeout', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*'], true);
        }

        if (in_array($module, ['check_cid', 'cidlistmatch'], true)) {
            return in_array($branch, ['match', 'nomatch'], true);
        }

        return $module === 'temporal_route' && $branch === 'rule_set';
    }

    /** @param array<string, mixed> $node */
    public static function childrenAreLocked(array $node): bool
    {
        $settings = self::settings($node);

        return in_array($node['module'] ?? null, self::LOCKED_MODULES, true)
            || (($node['module'] ?? null) === 'check_cid'
                && ($settings['use_absolute_mode'] ?? false) === true)
            || (($node['module'] ?? null) === 'branch_variable'
                && ! self::supportsCallPriority($node));
    }

    public static function isGuidedModule(string $module): bool
    {
        return in_array($module, self::GUIDED_MODULES, true);
    }

    public static function isTerminalModule(string $module): bool
    {
        return in_array($module, self::TERMINAL_MODULES, true);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{
     *     accepts_children: bool,
     *     default_branch_available: bool,
     *     branch_mode: 'continuation'|'menu'|'condition'|'temporal'|'priority'|'captured_number'|'terminal'|'locked',
     *     reason: string|null
     * }
     */
    public static function dropCapability(array $node): array
    {
        $branchMode = self::branchMode($node);
        $reason = self::dropBlockedReason($node);
        $defaultBranchAvailable = $reason === null && self::branchIsAvailable($node, '_');
        $acceptsChildren = $reason === null && self::hasAvailableBranch($node);

        if (! $acceptsChildren && $reason === null) {
            $reason = self::occupiedBranchReason($node);
        }

        return [
            'accepts_children' => $acceptsChildren,
            'default_branch_available' => $defaultBranchAvailable,
            'branch_mode' => $branchMode,
            'reason' => $reason,
        ];
    }

    /** @param array<string, mixed> $node */
    public static function supportsCallPriority(array $node): bool
    {
        $settings = self::settings($node);

        if (array_key_exists('supported_variable', $settings)) {
            return $settings['supported_variable'] === true;
        }

        return ($settings['variable'] ?? null) === 'call_priority'
            && in_array($settings['scope'] ?? 'custom_channel_vars', ['custom_channel_vars'], true);
    }

    public static function isPriorityKey(string $key): bool
    {
        return preg_match('/^(?:0|[1-9]\d{0,2})$/', $key) === 1 && (int) $key <= 255;
    }

    public static function isCapturedNumberKey(string $key): bool
    {
        return preg_match('/^[0-9*#+]{1,64}$/', $key) === 1;
    }

    /** @param array<string, mixed> $node */
    private static function dropBlockedReason(array $node): ?string
    {
        $module = is_string($node['module'] ?? null) ? $node['module'] : '';

        if (! self::isGuidedModule($module)) {
            return 'This action is not supported by the guided callflow editor.';
        }

        if (self::isTerminalModule($module)) {
            return 'This Switch action is terminal and cannot accept another action.';
        }

        if (($node['reference_status'] ?? null) === 'unresolved') {
            return 'Resolve this action reference before attaching another action.';
        }

        if (self::childrenAreLocked($node)) {
            return 'This conditional action has preserved branches that cannot be edited.';
        }

        return null;
    }

    /** @param array<string, mixed> $node */
    private static function hasAvailableBranch(array $node): bool
    {
        if (self::branchIsAvailable($node, '_')) {
            return true;
        }

        $module = is_string($node['module'] ?? null) ? $node['module'] : '';

        if ($module === 'menu') {
            foreach (['timeout', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*'] as $branch) {
                if (self::branchIsAvailable($node, $branch)) {
                    return true;
                }
            }

            return false;
        }

        if (in_array($module, ['check_cid', 'cidlistmatch'], true)) {
            return self::branchIsAvailable($node, 'match')
                || self::branchIsAvailable($node, 'nomatch');
        }

        if ($module === 'temporal_route') {
            return self::branchIsAvailable($node, 'rule_set');
        }

        if ($module === 'branch_variable' && self::supportsCallPriority($node)) {
            for ($priority = 0; $priority <= 255; $priority++) {
                if (self::branchIsAvailable($node, (string) $priority)) {
                    return true;
                }
            }

            return false;
        }

        if ($module === 'branch_bnumber') {
            $settings = self::settings($node);

            return ($settings['hunt'] ?? false) !== true;
        }

        return false;
    }

    /** @param array<string, mixed> $node */
    private static function branchIsAvailable(array $node, string $branch): bool
    {
        if (! self::supports($node, $branch)) {
            return false;
        }

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];

        return ! array_key_exists($branch, $children);
    }

    /** @param array<string, mixed> $node */
    private static function occupiedBranchReason(array $node): string
    {
        if (($node['module'] ?? null) === 'set_variables') {
            // Monster declares Set CAV with no quantity rule and therefore
            // highlights an occupied node as a valid destination. Both
            // children serialize to the same `_` key, however, so saving the
            // callflow silently discards one subtree. GridPBX permits the
            // Switch-compatible empty case while protecting existing routing.
            return 'Set CAV already has a next step. Remove or move it before attaching another action.';
        }

        return 'All editable branches on this Switch action are occupied.';
    }

    /**
     * @param  array<string, mixed>  $node
     * @return 'continuation'|'menu'|'condition'|'temporal'|'priority'|'captured_number'|'terminal'|'locked'
     */
    private static function branchMode(array $node): string
    {
        $module = is_string($node['module'] ?? null) ? $node['module'] : '';

        return match (true) {
            self::isTerminalModule($module) => 'terminal',
            self::childrenAreLocked($node) => 'locked',
            $module === 'menu' => 'menu',
            in_array($module, ['check_cid', 'cidlistmatch'], true) => 'condition',
            $module === 'temporal_route' => 'temporal',
            $module === 'branch_variable' => 'priority',
            $module === 'branch_bnumber' => 'captured_number',
            default => 'continuation',
        };
    }

    /** @param array<string, mixed> $node @return array<string, mixed> */
    private static function settings(array $node): array
    {
        if (is_array($node['settings'] ?? null)) {
            return $node['settings'];
        }

        return is_array($node['data'] ?? null) ? $node['data'] : [];
    }
}
