<?php

namespace App\Shared\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeSwitchRegex implements ValidationRule
{
    public static function isSafe(mixed $value): bool
    {
        return is_string($value)
            && $value !== ''
            && ! str_contains($value, "\x1F")
            && preg_match('/\(\?(?:R|0|&|P>|\{|\?)/', $value) !== 1
            && ! str_contains($value, '(*')
            && @preg_match("\x1F{$value}\x1Fu", '') !== false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        // Keep the editable subset portable and exclude recursive or executable PCRE constructs.
        if (str_contains($value, "\x1F")
            || preg_match('/\(\?(?:R|0|&|P>|\{|\?)/', $value) === 1
            || str_contains($value, '(*')) {
            $fail('The :attribute contains an unsupported regular-expression construct.');

            return;
        }

        if (@preg_match("\x1F{$value}\x1Fu", '') === false) {
            $fail('The :attribute must be a valid regular expression.');
        }
    }
}
