<?php

namespace App\Domains\CallDetailRecords\Requests;

use DateTimeImmutable;
use DateTimeInterface;
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
            'started_after' => ['nullable', 'date_format:Y-m-d\TH:i:sP'],
            'started_before' => ['nullable', 'date_format:Y-m-d\TH:i:sP'],
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

            $after = $this->input('started_after');
            $before = $this->input('started_before');

            if (
                is_string($after)
                && is_string($before)
                && ! $validator->errors()->hasAny(['started_after', 'started_before'])
            ) {
                $afterAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $after);
                $beforeAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $before);

                if ($afterAt !== false && $beforeAt !== false && $afterAt >= $beforeAt) {
                    $validator->errors()->add(
                        'started_before',
                        'The precise end time must be after the precise start time.',
                    );
                }
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
