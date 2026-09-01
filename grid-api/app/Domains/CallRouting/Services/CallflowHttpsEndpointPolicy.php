<?php

namespace App\Domains\CallRouting\Services;

/**
 * Applies the common minimum URL policy for Switch-triggered outbound requests.
 *
 * DNS rebinding and redirect enforcement must also be handled at the network
 * boundary; this policy prevents administrators from saving obvious local or
 * private literal targets in GridPBX.
 */
final class CallflowHttpsEndpointPolicy
{
    public function allows(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! is_string($parts['host'] ?? null)) {
            return false;
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        $isIpAddress = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isPublicIpAddress = filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;

        $isBlockedHostname = ! $isIpAddress && (
            ! str_contains($host, '.')
            || ctype_digit($host)
            || $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.home.arpa')
        );

        return ($parts['scheme'] ?? null) === 'https'
            && $host !== ''
            && ! $isBlockedHostname
            && (! $isIpAddress || $isPublicIpAddress)
            && (! array_key_exists('port', $parts) || $parts['port'] === 443)
            && ! array_key_exists('user', $parts)
            && ! array_key_exists('pass', $parts)
            && ! array_key_exists('fragment', $parts);
    }
}
