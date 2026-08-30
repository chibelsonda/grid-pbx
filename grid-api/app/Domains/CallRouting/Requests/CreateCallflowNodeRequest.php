<?php

namespace App\Domains\CallRouting\Requests;

use App\Domains\CallRouting\Rules\CallflowPublicBranchRule;
use App\Domains\CallRouting\Rules\GuidedCallflowDestinationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateCallflowNodeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'parent_path' => ['present', 'array', 'max:32'],
            'parent_path.*' => ['required', 'string', new CallflowPublicBranchRule],
            'branch' => ['required', 'string', new CallflowPublicBranchRule],
            'destination_type' => ['required', 'string', new GuidedCallflowDestinationRule],
            'destination_id' => ['required', 'uuid'],
        ];
    }
}
