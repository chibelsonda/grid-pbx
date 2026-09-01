<?php

namespace App\Domains\Conferences\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConferenceParticipantControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'participant_id' => ['required', 'string', 'max:4096'],
            'action' => ['required', Rule::in(['mute', 'unmute', 'deaf', 'undeaf', 'kick'])],
        ];
    }
}
