<?php

namespace App\Domains\Devices\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Arr;

class DeviceMutationDataFactory
{
    /**
     * Translate public API input into the Switch-facing Device domain contract.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function make(
        SwitchAccount $account,
        array $data,
        ?string $ownerSwitchResourceId = null,
    ): array {
        $extension = $ownerSwitchResourceId === null && isset($data['assigned_extension_id'])
            ? $account->extensions()->where('id', $data['assigned_extension_id'])->firstOrFail()
            : null;

        $mutation = [
            'name' => $data['name'],
            'device_type' => $data['device_type'],
            'is_enabled' => $data['is_enabled'],
            'owner_switch_resource_id' => $ownerSwitchResourceId ?? $extension?->switch_resource_id,
            'make' => Arr::get($data, 'provision.endpoint_brand', $data['make'] ?? null),
            'family' => Arr::get($data, 'provision.endpoint_family'),
            'model' => Arr::get($data, 'provision.endpoint_model', $data['model'] ?? null),
            'mac_address' => $data['mac_address'] ?? null,
            'sip_username' => Arr::get($data, 'sip.username', $data['sip_username'] ?? null),
            'sip_password' => Arr::get($data, 'sip.password', $data['sip_password'] ?? null),
        ];

        if (array_key_exists('music_on_hold', $data)) {
            $mediaId = Arr::get($data, 'music_on_hold.media_id');
            $mutation['music_on_hold'] = [
                'media_id' => is_string($mediaId)
                    ? $account->media()->where('id', $mediaId)->value('switch_resource_id')
                    : null,
            ];
        }

        foreach ([
            'call_forward',
            'sip',
            'media',
            'caller_id',
            'caller_id_options',
            'call_waiting',
            'do_not_disturb',
            'contact_list',
            'exclude_from_queues',
            'language',
            'timezone',
            'presence_id',
            'mwi_unsolicited_updates',
            'register_overwrite_notify',
            'suppress_unregister_notifications',
            'ringtones',
            'call_restriction',
            'call_recording',
            'outbound_flags',
            'dial_plan',
            'metaflows',
            'flags',
            'formatters',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $mutation[$field] = $data[$field];
            }
        }

        if (isset($data['provision']) && is_array($data['provision'])) {
            $provisioning = Arr::only($data['provision'], [
                'id',
                'check_sync_event',
                'check_sync_reload',
                'check_sync_reboot',
            ]);

            if ($provisioning !== []) {
                $mutation['provision'] = $provisioning;
            }
        }

        return $mutation;
    }
}
