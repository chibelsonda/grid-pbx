<?php

namespace App\Domains\Devices\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'device_type' => ['required', 'string', 'max:64'],
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'mac_address' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/',
            ],
            'is_enabled' => ['required', 'boolean'],
            'assigned_extension_id' => [
                'nullable',
                'string',
                Rule::exists('switch_extensions', 'id')
                    ->where('switch_account_id', (string) $this->route('account')),
            ],
            'sip_username' => ['nullable', 'string', 'max:128'],
            'sip_password' => ['nullable', 'string', 'min:12', 'max:255'],
        ];
    }
}
