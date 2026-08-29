<?php

namespace App\Domains\PhoneNumbers\Resources;

use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchPhoneNumber */
class PhoneNumberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $switchJson = is_array($this->switch_json) ? $this->switch_json : [];
        $availableFeatures = $this->availableFeatures($switchJson);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'state' => $this->state,
            'used_by' => $this->used_by,
            'carrier_name' => $this->carrier_name,
            'features' => $this->features ?? [],
            'cnam' => [
                'display_name' => $this->cnam_display_name,
                'inbound_lookup' => $this->cnam_inbound_lookup,
            ],
            'e911' => $this->e911($switchJson),
            'porting' => $this->porting($switchJson),
            'capabilities' => [
                'available_features' => $availableFeatures,
                'cnam' => $this->featureCapability('cnam', $availableFeatures),
                'e911' => $this->featureCapability('e911', $availableFeatures),
                'porting' => $this->featureCapability('port', $availableFeatures),
                'purchasing' => $this->disabledCarrierCapability(),
                'release' => $this->disabledCarrierCapability(),
            ],
            'assigned_callflow' => $this->assignedCallflow === null ? null : [
                'id' => $this->assignedCallflow->id,
                'name' => $this->assignedCallflow->name,
                'numbers' => $this->assignedCallflow->numbers,
            ],
            'sync_status' => $this->sync_status->value,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $switchJson
     * @return list<string>
     */
    private function availableFeatures(array $switchJson): array
    {
        $readOnly = is_array($switchJson['_read_only'] ?? null) ? $switchJson['_read_only'] : [];
        $features = $readOnly['features'] ?? null;
        $available = is_array($features) && array_key_exists('available', $features)
            ? $features['available']
            : ($readOnly['features_available'] ?? ($switchJson['features_available'] ?? []));

        return array_values(array_unique(array_filter(
            is_array($available) ? $available : [],
            static fn (mixed $feature): bool => is_string($feature) && $feature !== '',
        )));
    }

    /** @param array<string, mixed> $switchJson
     * @return array<string, mixed>
     */
    private function e911(array $switchJson): array
    {
        $e911 = is_array($switchJson['e911'] ?? null) ? $switchJson['e911'] : [];

        return [
            'status' => $this->stringValue($e911['status'] ?? null) ?? $this->e911_status,
            'caller_name' => $this->stringValue($e911['caller_name'] ?? null),
            'street_address' => $this->stringValue($e911['street_address'] ?? null),
            'extended_address' => $this->stringValue($e911['extended_address'] ?? null),
            'locality' => $this->stringValue($e911['locality'] ?? null),
            'region' => $this->stringValue($e911['region'] ?? null),
            'postal_code' => $this->stringValue($e911['postal_code'] ?? null),
            'notification_contact_emails' => array_values(array_unique(array_filter(
                is_array($e911['notification_contact_emails'] ?? null)
                    ? $e911['notification_contact_emails']
                    : [],
                static fn (mixed $email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
            ))),
        ];
    }

    /** @param array<string, mixed> $switchJson
     * @return array<string, mixed>
     */
    private function porting(array $switchJson): array
    {
        $porting = is_array($switchJson['porting'] ?? null) ? $switchJson['porting'] : [];

        return [
            'active' => in_array($this->state, ['port_in', 'port_out'], true) || $porting !== [],
            'requested_port_date' => $this->stringValue($porting['requested_port_date'] ?? null),
            'service_provider' => $this->stringValue($porting['service_provider'] ?? null),
        ];
    }

    /** @param list<string> $availableFeatures
     * @return array{available: bool, writable: bool, reason: string}
     */
    private function featureCapability(string $feature, array $availableFeatures): array
    {
        $available = in_array($feature, $availableFeatures, true);

        return [
            'available' => $available,
            'writable' => false,
            'reason' => $available
                ? 'Supported by Switch; mutation remains disabled until billing and compliance policy is configured.'
                : 'The connected Switch does not report this feature as available for the number.',
        ];
    }

    /** @return array{available: bool, writable: bool, reason: string} */
    private function disabledCarrierCapability(): array
    {
        return [
            'available' => false,
            'writable' => false,
            'reason' => 'Carrier operation is disabled until provider, billing, and confirmation policy is configured.',
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
