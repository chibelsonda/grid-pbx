<?php

namespace App\Domains\Organizations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'confirmation' => ['required', 'string', 'max:128'],
        ];
    }
}
