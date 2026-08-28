<?php

namespace App\Domains\LineKeys\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListLineKeysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100']];
    }
}
