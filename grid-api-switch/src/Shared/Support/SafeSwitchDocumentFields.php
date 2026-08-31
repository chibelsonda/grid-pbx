<?php

declare(strict_types=1);

namespace GridPbx\Switch\Shared\Support;

final class SafeSwitchDocumentFields
{
    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function from(array $values): array
    {
        $safe = [];
        $isList = array_is_list($values);

        foreach ($values as $key => $value) {
            if ((is_string($key)
                    && (str_starts_with($key, '_') || str_starts_with($key, 'pvt_')))
                || $value === '[REDACTED]') {
                continue;
            }

            $safe[$key] = is_array($value) ? self::from($value) : $value;
        }

        return $isList ? array_values($safe) : $safe;
    }
}
