<?php

namespace App\Domains\IdentityAccess\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:1024', 'current_password:web'],
            'password' => [
                'required',
                'string',
                'min:12',
                'max:1024',
                'different:current_password',
            ],
            'password_confirmation' => ['required', 'string', 'max:1024', 'same:password'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Enter your current password.',
            'current_password.string' => 'Enter your current password.',
            'current_password.max' => 'The current password may not exceed 1024 characters.',
            'current_password.current_password' => 'The current password is incorrect.',
            'password.required' => 'Enter a new password.',
            'password.string' => 'Enter a new password.',
            'password.min' => 'Use at least 12 characters for your new password.',
            'password.max' => 'The new password may not exceed 1024 characters.',
            'password.different' => 'Choose a new password that differs from your current password.',
            'password_confirmation.required' => 'Confirm your new password.',
            'password_confirmation.string' => 'Confirm your new password.',
            'password_confirmation.max' => 'The password confirmation may not exceed 1024 characters.',
            'password_confirmation.same' => 'The new password confirmation does not match.',
        ];
    }
}
