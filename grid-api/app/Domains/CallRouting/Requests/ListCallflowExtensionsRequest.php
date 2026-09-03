<?php

namespace App\Domains\CallRouting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListCallflowExtensionsRequest extends FormRequest
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
            'callflow_id' => ['nullable', 'uuid'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
