<?php

namespace App\Domains\Organizations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountSettingsRequest extends FormRequest
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
            'organization_name' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'language' => ['nullable', 'string', 'max:32'],
            'call_waiting_enabled' => ['required', 'boolean'],
            'do_not_disturb_enabled' => ['required', 'boolean'],
            'outbound_privacy' => ['required', Rule::in(['full', 'name', 'number', 'none'])],
            'show_rate' => ['required', 'boolean'],
            'ringtone_internal' => ['nullable', 'string', 'max:255'],
            'ringtone_external' => ['nullable', 'string', 'max:255'],
            'caller_id' => ['required', 'array:internal,external,emergency'],
            'caller_id.internal' => ['required', 'array:name,number'],
            'caller_id.internal.name' => ['nullable', 'string', 'max:35'],
            'caller_id.internal.number' => ['nullable', 'string', 'max:35'],
            'caller_id.external' => ['required', 'array:name,phone_number_id,preserve_number'],
            'caller_id.external.name' => ['nullable', 'string', 'max:35'],
            'caller_id.external.phone_number_id' => ['nullable', 'uuid'],
            'caller_id.external.preserve_number' => ['required', 'boolean'],
            'caller_id.emergency' => ['required', 'array:name,phone_number_id,preserve_number'],
            'caller_id.emergency.name' => ['nullable', 'string', 'max:35'],
            'caller_id.emergency.phone_number_id' => ['nullable', 'uuid'],
            'caller_id.emergency.preserve_number' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter an account name.',
            'outbound_privacy.required' => 'Select an outbound privacy policy.',
            'outbound_privacy.in' => 'Select a valid outbound privacy policy.',
        ];
    }
}
