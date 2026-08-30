<?php

namespace App\Domains\CallRouting\Services;

final class CallflowBranchPolicy
{
    /** @var list<string> */
    private const FIXED_PUBLIC_KEYS = [
        '_', 'timeout', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*',
        'rule_set', 'match', 'nomatch',
    ];

    public static function isPublicKey(string $key): bool
    {
        return in_array($key, self::FIXED_PUBLIC_KEYS, true) || self::isPriorityKey($key);
    }

    /** @param array<string, mixed> $node */
    public static function supports(array $node, string $branch): bool
    {
        $module = is_string($node['module'] ?? null) ? $node['module'] : '';

        if ($module === 'branch_variable') {
            return self::supportsCallPriority($node)
                && ($branch === '_' || self::isPriorityKey($branch));
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

        return (($node['module'] ?? null) === 'check_cid'
                && ($settings['use_absolute_mode'] ?? false) === true)
            || (($node['module'] ?? null) === 'branch_variable'
                && ! self::supportsCallPriority($node));
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

    /** @param array<string, mixed> $node @return array<string, mixed> */
    private static function settings(array $node): array
    {
        if (is_array($node['settings'] ?? null)) {
            return $node['settings'];
        }

        return is_array($node['data'] ?? null) ? $node['data'] : [];
    }
}
