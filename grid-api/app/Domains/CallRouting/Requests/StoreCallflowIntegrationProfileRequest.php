<?php

namespace App\Domains\CallRouting\Requests;

use App\Domains\CallRouting\Enums\CallflowIntegrationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallflowIntegrationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = $this->integrationType();

        return [
            'integration_type' => ['required', Rule::in([
                CallflowIntegrationType::Pivot->value,
                CallflowIntegrationType::Webhook->value,
                CallflowIntegrationType::Disa->value,
                CallflowIntegrationType::GlobalCarrier->value,
                CallflowIntegrationType::AccountCarrier->value,
            ])],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            ...$this->settingsRules('required', $type),
        ];
    }

    /** @return array<string, mixed> */
    protected function settingsRules(string $presence, ?CallflowIntegrationType $type = null): array
    {
        return match ($type) {
            CallflowIntegrationType::Disa => [
                'settings' => [$presence, 'array:pin,retries,interdigit_ms,max_digits,preconnect_audio'],
                'settings.pin' => ['required', 'string', 'regex:/^\d{8,12}$/'],
                'settings.retries' => ['required', 'integer', 'between:1,3'],
                'settings.interdigit_ms' => ['required', 'integer', 'between:1000,5000'],
                'settings.max_digits' => ['required', 'integer', 'between:3,15'],
                'settings.preconnect_audio' => ['required', 'string', Rule::in(['dialtone', 'ringing'])],
            ],
            CallflowIntegrationType::Webhook => [
                'settings' => [$presence, 'array:uri,methods,max_retries'],
                'settings.uri' => ['required', 'string', 'max:2048'],
                'settings.methods' => ['required', 'array', 'min:1', 'max:2'],
                'settings.methods.*' => ['required', 'string', 'distinct', Rule::in(['get', 'post'])],
                'settings.max_retries' => ['required', 'integer', 'between:1,5'],
            ],
            CallflowIntegrationType::GlobalCarrier => [
                'settings' => [$presence === 'required' ? 'present' : $presence, 'array', 'max:0'],
            ],
            CallflowIntegrationType::AccountCarrier => [
                'settings' => [$presence, 'array:scope'],
                'settings.scope' => ['required', 'string', Rule::in(['account', 'reseller'])],
            ],
            default => [
                'settings' => [$presence, 'array:voice_url,cdr_url,methods,formats,req_body_format,req_timeout_ms,custom_request_headers'],
                'settings.voice_url' => ['required', 'string', 'max:2048'],
                'settings.cdr_url' => ['nullable', 'string', 'max:2048'],
                'settings.methods' => ['required', 'array', 'min:1', 'max:2'],
                'settings.methods.*' => ['required', 'string', 'distinct', Rule::in(['get', 'post'])],
                'settings.formats' => ['required', 'array', 'min:1', 'max:2'],
                'settings.formats.*' => ['required', 'string', 'distinct', Rule::in(['kazoo', 'twiml'])],
                'settings.req_body_format' => ['required', Rule::in(['form', 'json'])],
                'settings.req_timeout_ms' => ['required', 'integer', 'between:1,5000'],
                'settings.custom_request_headers' => ['present', 'array', 'max:20'],
            ],
        };
    }

    protected function integrationType(): ?CallflowIntegrationType
    {
        $value = $this->input('integration_type');

        return is_string($value) ? CallflowIntegrationType::tryFrom($value) : null;
    }
}
