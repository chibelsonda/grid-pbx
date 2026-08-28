<?php

namespace App\Domains\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:128'],
            'language' => ['nullable', 'string', 'max:35'],
            'streamable' => ['nullable', 'boolean'],
            'audio' => [
                'required',
                'file',
                'max:5120',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg',
                'extensions:mp3,wav,ogg',
            ],
        ];
    }
}
