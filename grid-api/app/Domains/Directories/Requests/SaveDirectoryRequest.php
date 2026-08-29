<?php

namespace App\Domains\Directories\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'confirm_match' => ['required', 'boolean'],
            'min_dtmf' => ['required', 'integer', 'min:1', 'max:20'],
            'max_dtmf' => ['required', 'integer', 'min:0', 'max:20'],
            'sort_by' => ['required', Rule::in(['first_name', 'last_name'])],
            'flags' => ['prohibited'],
            'member_ids' => ['present', 'array'],
            'member_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
