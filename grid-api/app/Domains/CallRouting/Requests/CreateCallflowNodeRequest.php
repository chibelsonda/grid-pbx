<?php

namespace App\Domains\CallRouting\Requests;

use App\Domains\CallRouting\Rules\CallflowPublicBranchRule;
use App\Domains\CallRouting\Rules\GuidedCallflowDestinationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCallflowNodeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hasEndpointSettings = in_array($this->input('destination_type'), ['extension', 'device'], true);
        $strictBoolean = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_bool($value)) {
                $fail("The {$attribute} field must be true or false.");
            }
        };

        return [
            'parent_path' => ['present', 'array', 'max:32'],
            'parent_path.*' => ['required', 'string', new CallflowPublicBranchRule],
            'branch' => ['required', 'string', new CallflowPublicBranchRule],
            'destination_type' => ['required', 'string', new GuidedCallflowDestinationRule],
            'destination_id' => ['required', 'uuid'],
            'data' => ['sometimes', Rule::prohibitedIf(! $hasEndpointSettings), 'array:timeout,can_call_self'],
            'data.timeout' => ['required_with:data', 'integer', 'min:1', 'max:600'],
            'data.can_call_self' => ['required_with:data', $strictBoolean],
        ];
    }
}
