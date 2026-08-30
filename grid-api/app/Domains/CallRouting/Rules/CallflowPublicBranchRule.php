<?php

namespace App\Domains\CallRouting\Rules;

use App\Domains\CallRouting\Services\CallflowBranchPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CallflowPublicBranchRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! CallflowBranchPolicy::isPublicKey($value)) {
            $fail('The :attribute contains a preserved or unsupported callflow branch.');
        }
    }
}
