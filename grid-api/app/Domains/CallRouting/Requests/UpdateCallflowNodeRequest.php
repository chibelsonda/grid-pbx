<?php

namespace App\Domains\CallRouting\Requests;

use App\Domains\CallRouting\Rules\CallflowPublicBranchRule;
use App\Domains\CallRouting\Rules\GuidedCallflowDestinationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCallflowNodeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'node_path' => ['required', 'array', 'min:1', 'max:32'],
            'node_path.*' => ['required', 'string', new CallflowPublicBranchRule],
            'destination_type' => ['required', 'string', new GuidedCallflowDestinationRule],
            'destination_id' => ['required', 'uuid'],
        ];
    }
}
