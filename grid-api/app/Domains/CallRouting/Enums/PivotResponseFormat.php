<?php

namespace App\Domains\CallRouting\Enums;

enum PivotResponseFormat: string
{
    case Switch = 'switch';
    case Twiml = 'twiml';

    private const LEGACY_SWITCH_VALUE = 'kazoo';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromStoredValue(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower($value);

        return $normalized === self::LEGACY_SWITCH_VALUE
            ? self::Switch
            : self::tryFrom($normalized);
    }

    public function toUpstreamValue(): string
    {
        return $this === self::Switch ? self::LEGACY_SWITCH_VALUE : $this->value;
    }
}
