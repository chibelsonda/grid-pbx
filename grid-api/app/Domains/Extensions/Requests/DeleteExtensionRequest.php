<?php

namespace App\Domains\Extensions\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', 'max:32'],
        ];
    }
}
