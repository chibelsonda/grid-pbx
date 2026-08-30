<?php

namespace App\Domains\CallRouting\Services;

final class RingGroupPolicy
{
    public const MAX_ENDPOINTS = 20;

    public const MAX_ENDPOINT_DELAY = 60;

    public const MAX_ENDPOINT_TIMEOUT = 60;

    public const MAX_ATTEMPT_TIMEOUT = 120;

    public const MAX_REPEATS = 3;

    /** @param list<array{delay: int, timeout: int}> $endpoints */
    public static function attemptTimeout(string $strategy, array $endpoints): int
    {
        if (in_array($strategy, ['single', 'weighted_random'], true)) {
            return array_sum(array_column($endpoints, 'timeout'));
        }

        return max(array_map(
            fn (array $endpoint): int => $endpoint['delay'] + $endpoint['timeout'],
            $endpoints,
        ));
    }
}
