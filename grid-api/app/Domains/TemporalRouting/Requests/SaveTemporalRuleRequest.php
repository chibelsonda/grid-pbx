<?php

namespace App\Domains\TemporalRouting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTemporalRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'], 'cycle' => ['required', Rule::in(['date', 'daily', 'weekly', 'monthly', 'yearly'])],
            'interval' => ['required', 'integer', 'min:1', 'max:365'], 'start_date' => ['nullable', 'date_format:Y-m-d'],
            'time_window_start' => ['nullable', 'integer', 'min:0', 'max:86400'], 'time_window_stop' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'enabled' => ['nullable', 'boolean'], 'days' => ['present', 'array'], 'days.*' => ['integer', 'min:1', 'max:31', 'distinct'],
            'weekdays' => ['present', 'array'], 'weekdays.*' => [Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']), 'distinct'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'], 'ordinal' => ['nullable', Rule::in(['every', 'first', 'second', 'third', 'fourth', 'fifth', 'last'])],
        ];
    }
}
