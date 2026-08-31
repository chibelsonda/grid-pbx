<?php

namespace App\Domains\Payments\Requests;

use Illuminate\Validation\Rule;

class CreateSandboxRefundRequest extends PaymentMutationRequest
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
                'max:'.(int) config('payments.authorize_net.sandbox_max_refund_minor', 100),
            ],
            'currency' => ['required', Rule::in(['USD'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...$this->mutationMessages(),
            'amount_minor.max' => 'The sandbox refund exceeds the configured safety limit.',
        ];
    }
}
