<?php

namespace App\Domains\Devices\Support;

final class MacAddress
{
    public static function canonicalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $identity = self::identity($value);

        return $identity === null
            ? $value
            : implode(':', str_split($identity, 2));
    }

    public static function identity(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(?:[0-9A-Fa-f]{12}|(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2})$/', $value) !== 1) {
            return null;
        }

        return strtoupper(str_replace([':', '-'], '', $value));
    }
}
