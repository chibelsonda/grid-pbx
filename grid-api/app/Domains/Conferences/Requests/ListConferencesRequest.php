<?php

namespace App\Domains\Conferences\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListConferencesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', 'in:active,inactive,locked'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
