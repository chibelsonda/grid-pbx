<?php

namespace App\Domains\Dashboard\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CallActivityTrendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'range' => ['sometimes', 'string', Rule::in(['today', '7d', '30d'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'range.in' => 'The selected call activity range is invalid.',
        ];
    }

    public function activityRange(): string
    {
        $range = $this->validated('range');

        return is_string($range) ? $range : '7d';
    }
}
