<?php

namespace App\Domains\TemporalRouting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveTemporalRuleSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:128'], 'rule_ids' => ['required', 'array', 'min:1'], 'rule_ids.*' => ['required', 'uuid', 'distinct'], 'flags' => ['prohibited']];
    }
}
