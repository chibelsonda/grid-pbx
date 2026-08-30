<?php

namespace App\Domains\CallRouting\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GuidedCallflowDestinationRule implements ValidationRule
{
    private const TYPES = [
        'extension',
        'device',
        'voicemail',
        'callflow',
        'media',
        'directory',
        'group',
        'queue',
        'menu',
        'conference',
        'fax_box',
        'temporal_rule_set',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! in_array($value, self::TYPES, true)) {
            $fail('The selected :attribute is not available in the guided callflow editor.');
        }
    }
}
