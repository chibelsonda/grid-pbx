<?php

namespace App\Domains\Payments\Requests;

use Illuminate\Validation\Rule;

class CreateSandboxChargeRequest extends PaymentMutationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->mutationRules(),
            'amount_minor' => [
                'required',
                'integer',
                'min:1',
                'max:'.(int) config('payments.authorize_net.sandbox_max_charge_minor', 100),
            ],
            'currency' => ['required', Rule::in(['USD'])],
            'opaque_data' => ['required', 'array:dataDescriptor,dataValue'],
            'opaque_data.dataDescriptor' => ['required', Rule::in(['COMMON.ACCEPT.INAPP.PAYMENT'])],
            'opaque_data.dataValue' => ['required', 'string', 'min:16', 'max:4096'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...$this->mutationMessages(),
            'amount_minor.max' => 'The sandbox charge exceeds the configured safety limit.',
            'opaque_data.dataDescriptor.in' => 'The payment token type is not supported.',
        ];
    }
}
