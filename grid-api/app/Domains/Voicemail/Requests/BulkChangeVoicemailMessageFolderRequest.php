<?php

namespace App\Domains\Voicemail\Requests;

use App\Domains\Voicemail\Enums\VoicemailMessageFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkChangeVoicemailMessageFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'message_ids' => ['required', 'array', 'min:1', 'max:100'],
            'message_ids.*' => ['required', 'string', 'uuid', 'distinct'],
            'folder' => ['required', Rule::enum(VoicemailMessageFolder::class)],
        ];
    }
}
