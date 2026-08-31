<?php

namespace App\Domains\Conferences\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveConferenceRequest extends FormRequest
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
            'owner_id' => ['nullable', 'uuid'],
            'conference_numbers' => ['present', 'array', 'max:20'],
            'conference_numbers.*' => ['string', 'regex:/^[0-9]+$/', 'max:32', 'distinct'],
            'member_numbers' => ['present', 'array', 'max:20'],
            'member_numbers.*' => ['string', 'regex:/^[0-9]+$/', 'max:32', 'distinct'],
            'moderator_numbers' => ['present', 'array', 'max:20'],
            'moderator_numbers.*' => ['string', 'regex:/^[0-9]+$/', 'max:32', 'distinct'],
            'member_pins' => ['present', 'array', 'max:20'],
            'member_pins.*' => ['string', 'regex:/^[0-9]{1,32}$/', 'distinct'],
            'clear_member_pin' => ['required', 'boolean'],
            'moderator_pins' => ['present', 'array', 'max:20'],
            'moderator_pins.*' => ['string', 'regex:/^[0-9]{1,32}$/', 'distinct'],
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
            'max_members_media_id' => ['nullable', 'uuid'],
            'play_entry_tone_mode' => ['required', Rule::in(['enabled', 'disabled', 'media', 'current_custom'])],
            'play_entry_tone_media_id' => ['nullable', 'uuid', 'required_if:play_entry_tone_mode,media'],
            'play_exit_tone_mode' => ['required', Rule::in(['enabled', 'disabled', 'media', 'current_custom'])],
            'play_exit_tone_media_id' => ['nullable', 'uuid', 'required_if:play_exit_tone_mode,media'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['member', 'moderator'] as $role) {
                if ($this->boolean("clear_{$role}_pin") && $this->input("{$role}_pins", []) !== []) {
                    $validator->errors()->add("{$role}_pins", ucfirst($role).' PINs cannot be replaced and removed together.');
                }
            }
        }];
    }
}
