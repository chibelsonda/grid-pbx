<?php

namespace App\Domains\Recordings\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListRecordingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['search' => ['nullable', 'string', 'max:100'], 'direction' => ['nullable', Rule::in(['inbound', 'outbound'])], 'started_from' => ['nullable', 'date_format:Y-m-d'], 'started_to' => ['nullable', 'date_format:Y-m-d'], 'duration_min' => ['nullable', 'integer', 'min:0', 'max:86400'], 'duration_max' => ['nullable', 'integer', 'min:0', 'max:86400'], 'has_audio' => ['nullable', 'boolean'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]; }
    public function after(): array { return [function (Validator $validator): void { if (is_string($this->input('started_from')) && is_string($this->input('started_to')) && $this->input('started_from') > $this->input('started_to')) $validator->errors()->add('started_to', 'The end date must be on or after the start date.'); if (is_numeric($this->input('duration_min')) && is_numeric($this->input('duration_max')) && (int) $this->input('duration_min') > (int) $this->input('duration_max')) $validator->errors()->add('duration_max', 'The maximum duration must be greater than or equal to the minimum duration.'); }]; }
}
