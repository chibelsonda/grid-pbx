<?php

namespace App\Domains\TemporalRouting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemporalControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['enable', 'disable', 'reset'])]];
    }
}
