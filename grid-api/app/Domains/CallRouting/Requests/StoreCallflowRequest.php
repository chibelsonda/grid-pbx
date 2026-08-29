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
            'manage_fallback' => ['sometimes', 'boolean'],
            'fallback_destination_type' => ['nullable', 'required_with:fallback_destination_id', Rule::in(['extension', 'device', 'voicemail', 'callflow', 'media', 'directory', 'group', 'queue', 'menu', 'conference', 'fax_box', 'temporal_rule_set'])],
            'fallback_destination_id' => ['nullable', 'required_with:fallback_destination_type', 'uuid'],
            'manage_menu_branches' => ['sometimes', 'boolean'],
            'menu_branches' => ['required_if:manage_menu_branches,true', 'array', 'max:12'],
            'menu_branches.*.key' => ['required', 'string', 'distinct', Rule::in(['timeout', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*'])],
            'menu_branches.*.destination_type' => ['required', Rule::in(['extension', 'device', 'voicemail', 'callflow', 'media', 'directory', 'group', 'queue', 'menu', 'conference', 'fax_box', 'temporal_rule_set'])],
            'menu_branches.*.destination_id' => ['required', 'uuid'],
            'manage_temporal_match' => ['sometimes', 'boolean'],
            'temporal_match_destination_type' => ['nullable', 'required_with:temporal_match_destination_id', Rule::in(['extension', 'device', 'voicemail', 'callflow', 'media', 'directory', 'group', 'queue', 'menu', 'conference', 'fax_box', 'temporal_rule_set'])],
            'temporal_match_destination_id' => ['nullable', 'required_with:temporal_match_destination_type', 'uuid'],
            'phone_number_ids' => ['required', 'array', 'min:1', 'max:25'],
            'phone_number_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
