<?php

namespace App\Domains\Conferences\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConferenceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'owner_id' => ['nullable', 'uuid'],
            'conference_numbers' => ['required', 'array', 'max:20'],
            'conference_numbers.*' => ['string', 'regex:/^[0-9]+$/', 'max:32', 'distinct'],
            'member_numbers' => ['required', 'array', 'max:20'],
            'member_numbers.*' => ['string', 'regex:/^[0-9]+$/', 'max:32', 'distinct'],
            'moderator_numbers' => ['required', 'array', 'max:20'],
            'moderator_numbers.*' => ['string', 'regex:/^[0-9]+$/', 'max:32', 'distinct'],
            'member_pin' => ['nullable', 'string', 'regex:/^[0-9]{1,32}$/', Rule::prohibitedIf($this->boolean('clear_member_pin'))],
            'clear_member_pin' => ['required', 'boolean'],
            'moderator_pin' => ['nullable', 'string', 'regex:/^[0-9]{1,32}$/', Rule::prohibitedIf($this->boolean('clear_moderator_pin'))],
            'clear_moderator_pin' => ['required', 'boolean'],
            'member_join_muted' => ['required', 'boolean'],
            'member_join_deaf' => ['required', 'boolean'],
            'member_play_entry_prompt' => ['required', 'boolean'],
            'moderator_join_muted' => ['required', 'boolean'],
            'moderator_join_deaf' => ['required', 'boolean'],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'language' => ['nullable', 'string', 'max:16'],
            'profile_name' => ['nullable', 'string', 'max:128'],
            'caller_controls' => ['nullable', 'string', 'max:128'],
            'moderator_controls' => ['nullable', 'string', 'max:128'],
            'play_name' => ['required', 'boolean'],
            'play_welcome' => ['required', 'boolean'],
            'require_moderator' => ['required', 'boolean'],
            'wait_for_moderator' => ['required', 'boolean'],
        ];
    }
}
