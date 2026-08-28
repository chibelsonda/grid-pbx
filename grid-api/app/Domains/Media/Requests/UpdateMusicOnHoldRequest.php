<?php

namespace App\Domains\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMusicOnHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'media_id' => ['nullable', 'uuid'],
        ];
    }
}
