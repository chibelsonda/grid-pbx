<?php

namespace App\Domains\Conferences\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConferenceBulkParticipantControlRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['mute', 'unmute', 'deaf', 'undeaf'])],
            'expected_participant_count' => ['required', 'integer', 'min:1', 'max:10000'],
            'expected_target_count' => ['required', 'integer', 'min:1', 'max:10000'],
            'confirmation' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirmation.accepted' => 'Confirm the room-wide participant command before continuing.',
        ];
    }
}
