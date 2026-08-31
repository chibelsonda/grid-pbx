<?php

namespace App\Domains\Payments\Requests;

class CreateSandboxVoidRequest extends PaymentMutationRequest
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
