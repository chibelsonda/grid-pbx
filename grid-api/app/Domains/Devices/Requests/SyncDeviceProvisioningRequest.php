<?php

namespace App\Domains\Devices\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncDeviceProvisioningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'command' => ['required_without:reboot', 'string', Rule::in(['sync', 'reprovision'])],
            'reboot' => ['sometimes', 'boolean'],
        ];
    }

    public function command(): string
    {
        if ($this->filled('command')) {
            return $this->string('command')->toString();
        }

        return $this->boolean('reboot') ? 'reprovision' : 'sync';
    }
}
