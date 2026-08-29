<?php

namespace App\Domains\Menus\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMenuRequest extends FormRequest
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
            'timeout' => ['required', 'integer', 'min:1', 'max:60000'],
            'interdigit_timeout' => ['required', 'integer', 'min:1', 'max:10000'],
            'max_extension_length' => ['required', 'integer', 'min:1', 'max:6'],
            'retries' => ['required', 'integer', 'min:1', 'max:10'],
            'hunt' => ['required', 'boolean'],
            'allow_record_from_offnet' => ['required', 'boolean'],
            'suppress_media' => ['required', 'boolean'],
            'record_pin' => ['nullable', 'digits_between:3,6'],
            'hunt_allow' => ['nullable', 'string', 'max:256'],
            'hunt_deny' => ['nullable', 'string', 'max:256'],
            'greeting_media_id' => ['nullable', 'uuid'],
            'invalid_media_enabled' => ['required', 'boolean'],
            'invalid_media_id' => ['nullable', 'uuid'],
            'transfer_media_enabled' => ['required', 'boolean'],
            'transfer_media_id' => ['nullable', 'uuid'],
            'exit_media_enabled' => ['required', 'boolean'],
            'exit_media_id' => ['nullable', 'uuid'],
            'flags' => ['prohibited'],
        ];
    }
}
