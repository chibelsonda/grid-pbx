<?php

namespace App\Domains\Voicemail\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoicemailGreetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:128'],
            'audio' => [
                'required',
                'file',
                'max:10240',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg',
            ],
        ];
    }
}
