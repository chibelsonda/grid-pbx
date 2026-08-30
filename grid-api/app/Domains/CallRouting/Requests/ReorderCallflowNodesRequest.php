<?php

namespace App\Domains\CallRouting\Requests;

use App\Domains\CallRouting\Rules\CallflowPublicBranchRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCallflowNodesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['insert_before', 'swap'])],
            'source_path' => ['required', 'array', 'min:1', 'max:32'],
            'source_path.*' => ['required', 'string', new CallflowPublicBranchRule],
            'target_path' => ['required', 'array', 'min:1', 'max:32'],
            'target_path.*' => ['required', 'string', new CallflowPublicBranchRule],
        ];
    }
}
