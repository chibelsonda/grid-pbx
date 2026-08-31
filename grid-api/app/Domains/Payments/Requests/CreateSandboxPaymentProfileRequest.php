<?php

namespace App\Domains\Payments\Requests;

class CreateSandboxPaymentProfileRequest extends PaymentMutationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->mutationRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->mutationMessages();
    }
}
