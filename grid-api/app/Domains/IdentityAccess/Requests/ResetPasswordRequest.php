<?php

namespace App\Domains\IdentityAccess\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:2048'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'max:'.(int) config('identity_access.password.maximum_length'),
                Password::defaults(),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => Str::lower($this->string('email')->trim()->toString()),
            ]);
        }
    }
}
