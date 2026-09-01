<?php

namespace App\Domains\CallRouting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCallflowEntryPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone_number_ids' => ['present', 'array', 'max:25'],
            'phone_number_ids.*' => ['uuid', 'distinct'],
            'extension_numbers' => ['present', 'array', 'max:25'],
            'extension_numbers.*' => ['required', 'string', 'regex:/^[0-9]{2,15}$/', 'distinct'],
        ];
    }
}
