<?php

namespace App\Domains\Organizations\Resources;

use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchAccount */
class AccountDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $role = is_string($this->organization_role)
            ? OrganizationRole::tryFrom($this->organization_role)
            : null;
        $snapshot = is_array($this->switch_json) ? $this->switch_json : [];
        $callerId = is_array($snapshot['caller_id'] ?? null) ? $snapshot['caller_id'] : [];
        $callerIdOptions = is_array($snapshot['caller_id_options'] ?? null)
            ? $snapshot['caller_id_options']
            : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'realm' => $this->realm,
            'timezone' => $this->timezone,
            'enabled' => $this->is_enabled,
            'organization' => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ],
            'resource_counts' => [
                'extensions' => $this->extensions_count,
                'devices' => $this->devices_count,
                'phone_numbers' => $this->phone_numbers_count,
                'callflows' => $this->callflows_count,
                'voicemail_boxes' => $this->voicemail_boxes_count,
                'queues' => $this->queues_count,
                'media' => $this->media_count,
                'recordings' => $this->recordings_count,
            ],
            'configuration' => [
                'organization_name' => $this->org_name,
                'language' => $this->language,
                'call_waiting_enabled' => $this->call_waiting_enabled ?? true,
                'do_not_disturb_enabled' => $this->do_not_disturb_enabled ?? false,
                'outbound_privacy' => $this->outbound_privacy ?? 'none',
                'show_rate' => ($callerIdOptions['show_rate'] ?? false) === true,
                'ringtone_internal' => $this->ringtone_internal,
                'ringtone_external' => $this->ringtone_external,
                'caller_id' => [
                    'internal' => $this->callerIdText($callerId, 'internal'),
                    'external' => $this->callerIdNumber($callerId, 'external'),
                    'emergency' => $this->callerIdNumber($callerId, 'emergency'),
                ],
            ],
            'options' => [
                'caller_id_numbers' => $this->phoneNumbers
                    ->map(fn ($phoneNumber): array => [
                        'id' => $phoneNumber->id,
                        'number' => $phoneNumber->number,
                        'display_name' => $phoneNumber->cnam_display_name,
                        'e911_enabled' => $phoneNumber->isE911Enabled(),
                    ])
                    ->values()
                    ->all(),
            ],
            'projection' => [
                'status' => $this->sync_status,
                'version' => $this->projection_version,
                'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            ],
            'permissions' => [
                'can_manage_settings' => $role?->canManageAccountSettings() ?? false,
            ],
            'configuration_boundaries' => [
                'identity_defaults' => 'safe_fields_available',
                'calling_defaults' => 'safe_fields_available',
                'advanced_routing' => 'planned',
                'enable_disable' => 'implemented_confirmed',
                'billing_topup' => 'provider_required',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $callerId
     * @return array{name: ?string, number: ?string}
     */
    private function callerIdText(array $callerId, string $scope): array
    {
        $value = is_array($callerId[$scope] ?? null) ? $callerId[$scope] : [];

        return [
            'name' => $this->stringValue($value['name'] ?? null),
            'number' => $this->stringValue($value['number'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $callerId
     * @return array{name: ?string, phone_number_id: ?string, number: ?string, unresolved: bool}
     */
    private function callerIdNumber(array $callerId, string $scope): array
    {
        $value = $this->callerIdText($callerId, $scope);
        $phoneNumber = $value['number'] === null
            ? null
            : $this->phoneNumbers->firstWhere('number', $value['number']);

        return [
            'name' => $value['name'],
            'phone_number_id' => $phoneNumber?->id,
            'number' => $value['number'],
            'unresolved' => $value['number'] !== null && $phoneNumber === null,
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
