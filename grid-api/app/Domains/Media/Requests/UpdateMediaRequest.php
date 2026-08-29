<?php

namespace App\Domains\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
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
            'streamable' => ['required', 'boolean'],
            'media_source' => ['prohibited'],
            'content_type' => ['prohibited'],
            'content_length' => ['prohibited'],
            'prompt_id' => ['prohibited'],
            'source_id' => ['prohibited'],
            'source_type' => ['prohibited'],
            'tts' => ['prohibited'],
            'switch_json' => ['prohibited'],
        ];
    }
}
