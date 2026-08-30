<?php

namespace App\Domains\CallRouting\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CallflowPublicBranchRule implements ValidationRule
{
    private const KEYS = [
        '_',
        'timeout',
        '0',
        '1',
        '2',
        '3',
        '4',
        '5',
        '6',
        '7',
        '8',
        '9',
        '*',
        'rule_set',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! in_array($value, self::KEYS, true)) {
            $fail('The :attribute contains a preserved or unsupported callflow branch.');
        }
    }
}
