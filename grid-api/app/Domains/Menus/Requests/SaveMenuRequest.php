<?php

namespace App\Domains\Menus\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'record_pin' => ['nullable', 'digits_between:3,6', Rule::prohibitedIf($this->boolean('clear_record_pin'))],
            'clear_record_pin' => ['required', 'boolean'],
            'hunt_allow' => ['nullable', 'string', 'min:1', 'max:256'],
            'hunt_deny' => ['nullable', 'string', 'min:1', 'max:256'],
            'greeting_media_id' => ['nullable', 'uuid', Rule::prohibitedIf($this->boolean('clear_greeting_media'))],
            'clear_greeting_media' => ['required', 'boolean'],
            'invalid_media_enabled' => ['required', 'boolean'],
            'invalid_media_id' => ['nullable', 'uuid', Rule::prohibitedIf($this->boolean('clear_invalid_media'))],
            'clear_invalid_media' => ['required', 'boolean'],
            'transfer_media_enabled' => ['required', 'boolean'],
            'transfer_media_id' => ['nullable', 'uuid', Rule::prohibitedIf($this->boolean('clear_transfer_media'))],
            'clear_transfer_media' => ['required', 'boolean'],
            'exit_media_enabled' => ['required', 'boolean'],
            'exit_media_id' => ['nullable', 'uuid', Rule::prohibitedIf($this->boolean('clear_exit_media'))],
            'clear_exit_media' => ['required', 'boolean'],
            'flags' => ['prohibited'],
        ];
    }
}
