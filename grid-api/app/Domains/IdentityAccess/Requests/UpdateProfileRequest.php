<?php

namespace App\Domains\IdentityAccess\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter your display name.',
            'name.string' => 'Enter your display name.',
            'name.max' => 'Display names may not exceed 255 characters.',
        ];
    }
}
