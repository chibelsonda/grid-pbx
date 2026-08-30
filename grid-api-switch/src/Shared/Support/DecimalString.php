<?php

declare(strict_types=1);

namespace GridPbx\Switch\Shared\Support;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final class DecimalString
{
    public static function fromMixed(mixed $value, string $field): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value) && is_finite($value)) {
            $normalized = rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');

            return $normalized === '-0' ? '0' : $normalized;
        }

        if (is_string($value) && preg_match('/^-?\d+(?:\.\d+)?$/D', $value) === 1) {
            return $value;
        }

        throw new InvalidSwitchPayloadException("Switch {$field} must be a decimal number.");
    }

    public static function nullable(mixed $value, string $field): ?string
    {
        return $value === null ? null : self::fromMixed($value, $field);
    }
}
