<?php

namespace App\Domains\Voicemail\Requests;

use App\Domains\Voicemail\Enums\VoicemailMessageFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeVoicemailMessageFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'folder' => ['required', Rule::enum(VoicemailMessageFolder::class)],
        ];
    }
}
