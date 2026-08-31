<?php

namespace App\Domains\Payments\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class PaymentMutationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    protected function mutationRules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128'],
            'confirmation' => ['required', 'accepted'],
            'card' => ['prohibited'],
            'card_data' => ['prohibited'],
            'card_number' => ['prohibited'],
            'cardNumber' => ['prohibited'],
            'card_code' => ['prohibited'],
            'cardCode' => ['prohibited'],
            'cvv' => ['prohibited'],
            'expiration' => ['prohibited'],
            'expiry' => ['prohibited'],
            'provider_reference' => ['prohibited'],
            'transaction_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    protected function mutationMessages(): array
    {
        return [
            'idempotency_key.required' => 'An Idempotency-Key header is required.',
            'confirmation.accepted' => 'Explicit sandbox payment confirmation is required.',
            'card.prohibited' => 'Raw payment-card data must not be sent to GridPBX.',
            'card_data.prohibited' => 'Raw payment-card data must not be sent to GridPBX.',
            'card_number.prohibited' => 'Raw payment-card data must not be sent to GridPBX.',
            'cardNumber.prohibited' => 'Raw payment-card data must not be sent to GridPBX.',
            'card_code.prohibited' => 'Raw payment-card data must not be sent to GridPBX.',
            'cardCode.prohibited' => 'Raw payment-card data must not be sent to GridPBX.',
            'cvv.prohibited' => 'Raw payment-card data must not be sent to GridPBX.',
            'expiration.prohibited' => 'Raw payment-card data must not be sent to GridPBX.',
            'expiry.prohibited' => 'Raw payment-card data must not be sent to GridPBX.',
            'provider_reference.prohibited' => 'Provider references are resolved by GridPBX.',
            'transaction_id.prohibited' => 'Provider references are resolved by GridPBX.',
        ];
    }
}
