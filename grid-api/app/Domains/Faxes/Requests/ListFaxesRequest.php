<?php

namespace App\Domains\Faxes\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListFaxesRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['search' => ['nullable', 'string', 'max:128'], 'folder' => ['nullable', Rule::in(['inbox', 'outbox'])], 'status' => ['nullable', 'string', 'max:48'], 'fax_box_id' => ['nullable', 'uuid'], 'created_from' => ['nullable', 'date_format:Y-m-d'], 'created_to' => ['nullable', 'date_format:Y-m-d'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]; }
    public function after(): array { return [function (Validator $validator): void { if (is_string($this->input('created_from')) && is_string($this->input('created_to')) && $this->input('created_from') > $this->input('created_to')) $validator->errors()->add('created_to', 'The end date must be on or after the start date.'); }]; }
}
