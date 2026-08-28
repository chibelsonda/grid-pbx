<?php

namespace App\Domains\CallRouting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallflowRequest extends FormRequest
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
            'destination_type' => ['required', Rule::in(['extension', 'device', 'voicemail', 'callflow', 'media', 'directory', 'group', 'queue', 'menu', 'conference', 'fax_box', 'temporal_rule_set'])],
            'destination_id' => ['required', 'uuid'],
            'phone_number_ids' => ['required', 'array', 'min:1', 'max:25'],
            'phone_number_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
