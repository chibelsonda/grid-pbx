<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Support;

final class CallflowBranchPolicy
{
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

        if ($module === 'branch_variable') {
            return self::supportsCallPriority($node)
                && ($branch === '_' || self::isPriorityKey($branch));
        }

        if ($module === 'branch_bnumber') {
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];

            return $branch === '_'
                || (($data['hunt'] ?? false) !== true && self::isCapturedNumberKey($branch));
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
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        return (($node['module'] ?? null) === 'check_cid'
                && ($data['use_absolute_mode'] ?? false) === true)
            || (($node['module'] ?? null) === 'branch_variable'
                && ! self::supportsCallPriority($node));
    }

    /** @param array<string, mixed> $node */
    public static function supportsCallPriority(array $node): bool
    {
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        return ($data['variable'] ?? null) === 'call_priority'
            && in_array($data['scope'] ?? 'custom_channel_vars', ['custom_channel_vars'], true);
    }

    public static function isPriorityKey(string $key): bool
    {
        return preg_match('/^(?:0|[1-9]\d{0,2})$/', $key) === 1 && (int) $key <= 255;
    }

    public static function isCapturedNumberKey(string $key): bool
    {
        return preg_match('/^[0-9*#+]{1,64}$/', $key) === 1;
    }
}
