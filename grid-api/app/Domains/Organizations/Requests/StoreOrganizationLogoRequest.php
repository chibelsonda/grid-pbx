<?php

namespace App\Domains\Organizations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'max:2048',
                'mimetypes:image/png,image/jpeg,image/webp',
                'extensions:png,jpg,jpeg,webp',
                'dimensions:min_width=32,min_height=32,max_width=2048,max_height=2048',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'logo.max' => 'The organization logo must not exceed 2 MB.',
            'logo.mimetypes' => 'The organization logo must be a PNG, JPEG, or WebP image.',
            'logo.extensions' => 'The organization logo must use a PNG, JPG, JPEG, or WebP extension.',
            'logo.dimensions' => 'The organization logo must be between 32×32 and 2048×2048 pixels.',
        ];
    }
}
