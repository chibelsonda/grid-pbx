<?php

namespace App\Domains\Queues\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['login', 'logout', 'pause', 'resume', 'end_wrapup'])],
            'pause_timeout' => ['nullable', 'integer', 'min:0', 'max:86400', Rule::requiredIf($this->input('status') === 'pause')],
        ];
    }
}
