<?php

namespace App\Domains\CallRouting\Requests;

use App\Domains\CallRouting\Enums\CallflowIntegrationType;
use Illuminate\Validation\Rule;

class UpdateCallflowIntegrationProfileRequest extends StoreCallflowIntegrationProfileRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $settings = $this->settingsRules('sometimes', $this->settingsIntegrationType());

        foreach ($settings as $key => $rules) {
            if ($key !== 'settings') {
                array_unshift($settings[$key], 'required_with:settings');
                $settings[$key] = array_values(array_filter(
                    $settings[$key],
                    fn (mixed $rule): bool => $rule !== 'required' && $rule !== 'present',
                ));
            }
        }

        return [
            'integration_type' => ['sometimes', 'required', Rule::in([
                CallflowIntegrationType::Pivot->value,
                CallflowIntegrationType::Webhook->value,
                CallflowIntegrationType::Disa->value,
                CallflowIntegrationType::GlobalCarrier->value,
                CallflowIntegrationType::AccountCarrier->value,
            ])],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            ...$settings,
        ];
    }

    private function settingsIntegrationType(): ?CallflowIntegrationType
    {
        $type = $this->integrationType();

        if ($type !== null || ! is_array($this->input('settings'))) {
            return $type;
        }

        $settings = $this->input('settings');

        if (array_key_exists('uri', $settings)) {
            return CallflowIntegrationType::Webhook;
        }

        if (array_key_exists('pin', $settings)) {
            return CallflowIntegrationType::Disa;
        }

        if (array_key_exists('scope', $settings)) {
            return CallflowIntegrationType::AccountCarrier;
        }

        return CallflowIntegrationType::Pivot;
    }
}
