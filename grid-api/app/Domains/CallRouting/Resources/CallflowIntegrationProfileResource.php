<?php

namespace App\Domains\CallRouting\Resources;

use App\Domains\CallRouting\Enums\CallflowIntegrationType;
use App\Domains\CallRouting\Enums\PivotResponseFormat;
use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CallflowIntegrationProfile */
class CallflowIntegrationProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return [
            'id' => $this->id,
            'integration_type' => $this->integration_type->value,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'configuration' => $this->configuration($settings),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function configuration(array $settings): array
    {
        return match ($this->integration_type) {
            CallflowIntegrationType::Pivot => [
                'methods' => $settings['methods'] ?? [],
                'formats' => $this->responseFormats($settings['formats'] ?? []),
                'has_cdr_callback' => is_string($settings['cdr_url'] ?? null),
                'has_custom_headers' => ($settings['custom_request_headers'] ?? []) !== [],
            ],
            CallflowIntegrationType::Webhook => [
                'methods' => $settings['methods'] ?? [],
                'max_retries' => $settings['max_retries'] ?? null,
            ],
            CallflowIntegrationType::Disa => [
                'pin_configured' => is_string($settings['pin'] ?? null),
                'retries' => $settings['retries'] ?? null,
                'interdigit_ms' => $settings['interdigit_ms'] ?? null,
                'max_digits' => $settings['max_digits'] ?? null,
                'preconnect_audio' => $settings['preconnect_audio'] ?? null,
                'enforce_call_restriction' => true,
                'use_account_caller_id' => false,
            ],
            CallflowIntegrationType::GlobalCarrier => [
                'route_scope' => 'global',
            ],
            CallflowIntegrationType::AccountCarrier => [
                'route_scope' => $settings['scope'] ?? null,
            ],
            default => [],
        };
    }

    /** @return list<string> */
    private function responseFormats(mixed $formats): array
    {
        if (! is_array($formats) || ! array_is_list($formats)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $format): ?string => PivotResponseFormat::fromStoredValue($format)?->value,
            $formats,
        ))));
    }
}
