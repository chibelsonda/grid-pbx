<?php

namespace App\Domains\CallRouting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckCallflowExtensionAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'regex:/^[0-9]{2,15}$/'],
            'callflow_id' => ['nullable', 'uuid'],
        ];
    }
}
