<?php

namespace App\Domains\CallRouting\Requests;

use App\Domains\CallRouting\Rules\CallflowPublicBranchRule;
use Illuminate\Foundation\Http\FormRequest;

class MoveCallflowNodeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_path' => ['required', 'array', 'min:1', 'max:32'],
            'source_path.*' => ['required', 'string', new CallflowPublicBranchRule],
            'destination_parent_path' => ['present', 'array', 'max:32'],
            'destination_parent_path.*' => ['required', 'string', new CallflowPublicBranchRule],
            'destination_branch' => ['required', 'string', new CallflowPublicBranchRule],
        ];
    }
}
