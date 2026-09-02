<?php

namespace App\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StreamBinaryResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'download' => ['sometimes', 'boolean'],
        ];
    }
}
