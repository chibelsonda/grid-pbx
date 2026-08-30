<?php

namespace App\Domains\Organizations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardDescendantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:4096'],
            'confirmation' => ['required', 'string', 'max:255'],
            'acknowledge_existing_access' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference.required' => 'Select an unresolved descendant account.',
            'confirmation.required' => 'Enter the descendant account name to confirm onboarding.',
            'acknowledge_existing_access.accepted' => 'Acknowledge that existing organization members will inherit access to the onboarded account.',
        ];
    }
}
