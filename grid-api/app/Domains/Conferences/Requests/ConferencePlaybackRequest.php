<?php

namespace App\Domains\Conferences\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConferencePlaybackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'media_id' => ['required', 'uuid'],
            'participant_id' => ['nullable', 'string', 'max:4096'],
            'confirmation' => ['required', 'accepted'],
            'media_url' => ['prohibited'],
            'url' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirmation.accepted' => 'Confirm conference media playback before continuing.',
            'media_url.prohibited' => 'Conference playback accepts projected media only.',
            'url.prohibited' => 'Conference playback accepts projected media only.',
        ];
    }
}
