<?php

namespace App\Domains\CallRouting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCallflowsRequest extends FormRequest
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
            'module' => ['nullable', 'string', 'max:64'],
            'type' => ['nullable', Rule::in(['extension', 'phone_number', 'feature_code', 'pattern', 'unassigned'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
