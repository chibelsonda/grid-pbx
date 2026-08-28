<?php

namespace App\Domains\CallDetailRecords\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListCallDetailRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', Rule::in(['inbound', 'outbound'])],
            'outcome' => ['nullable', Rule::in(['answered', 'unanswered'])],
            'hangup_cause' => ['nullable', 'string', 'max:64'],
            'started_from' => ['nullable', 'date_format:Y-m-d'],
            'started_to' => ['nullable', 'date_format:Y-m-d'],
            'duration_min' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'duration_max' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $from = $this->input('started_from');
            $to = $this->input('started_to');

            if (is_string($from) && is_string($to) && $from > $to) {
                $validator->errors()->add('started_to', 'The end date must be on or after the start date.');
            }

            $minimum = $this->input('duration_min');
            $maximum = $this->input('duration_max');

            if (is_numeric($minimum) && is_numeric($maximum) && (int) $minimum > (int) $maximum) {
                $validator->errors()->add(
                    'duration_max',
                    'The maximum duration must be greater than or equal to the minimum duration.',
                );
            }
        }];
    }
}
