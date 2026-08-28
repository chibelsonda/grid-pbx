<?php

namespace App\Domains\Groups\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGroupRequest extends FormRequest
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
            'music_on_hold_media_id' => ['nullable', 'uuid'],
            'members' => ['present', 'array'],
            'members.*.type' => ['required', Rule::in(['user', 'device', 'group'])],
            'members.*.id' => ['required', 'uuid', 'distinct'],
            'members.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
