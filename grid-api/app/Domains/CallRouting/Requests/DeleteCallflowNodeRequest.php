<?php

namespace App\Domains\CallRouting\Requests;

use App\Domains\CallRouting\Rules\CallflowPublicBranchRule;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCallflowNodeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'node_path' => ['required', 'array', 'min:1', 'max:32'],
            'node_path.*' => ['required', 'string', new CallflowPublicBranchRule],
            'confirm_subtree' => ['required', 'accepted'],
        ];
    }
}
