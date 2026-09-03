<?php

namespace App\Domains\IdentityAccess\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class ForgotPasswordRequest extends FormRequest
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
