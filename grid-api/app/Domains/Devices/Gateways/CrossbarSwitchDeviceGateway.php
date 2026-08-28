<?php

namespace App\Domains\Devices\Gateways;

use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Dto\Devices\DeviceAdvancedData;
use GridPbx\Switch\Dto\Devices\DeviceCallerIdData;
use GridPbx\Switch\Dto\Devices\DeviceCallForwardData;
use GridPbx\Switch\Dto\Devices\DeviceCallRecordingData;
use GridPbx\Switch\Dto\Devices\DeviceCustomSipHeadersData;
use GridPbx\Switch\Dto\Devices\DeviceDialPlanData;
use GridPbx\Switch\Dto\Devices\DeviceMediaData;
use GridPbx\Switch\Dto\Devices\DeviceMetaflowsData;
use GridPbx\Switch\Dto\Devices\DeviceMusicOnHoldData;
use GridPbx\Switch\Dto\Devices\DeviceOutboundFlagsData;
use GridPbx\Switch\Dto\Devices\DeviceRecordingParametersData;
use GridPbx\Switch\Dto\Devices\DeviceRecordingSourceData;
use GridPbx\Switch\Dto\Devices\DeviceSipData;
use GridPbx\Switch\Dto\Devices\DeviceWriteData;
use GridPbx\Switch\Dto\PhoneNumbers\NumberClassifierSnapshot;
use GridPbx\Switch\Resources\DeviceResourceClient;
use GridPbx\Switch\Resources\PhoneNumberResourceClient;
use Illuminate\Support\Arr;

class CrossbarSwitchDeviceGateway implements SwitchDeviceGateway
{
    public function __construct(
        private readonly DeviceResourceClient $devices,
        private readonly PhoneNumberResourceClient $phoneNumbers,
    ) {}

    /** @return list<array{key: string, label: string, emergency: bool}> */
    public function restrictionClassifiers(SwitchAccount $account): array
    {
        return array_map(
            static fn (NumberClassifierSnapshot $classifier): array => [
                'key' => $classifier->key,
                'label' => $classifier->friendlyName,
                'emergency' => $classifier->emergency,
            ],
            $this->phoneNumbers->classifiers($account->switch_account_id),
        );
    }

    public function create(SwitchAccount $account, array $device): array
    {
        return $this->devices
            ->create($account->switch_account_id, $this->writeData($device, false))
            ->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $device): array
    {
        return $this->devices
            ->update($account->switch_account_id, $resourceId, $this->writeData($device, true))
            ->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->devices->delete($account->switch_account_id, $resourceId);
    }

    /** @param array<string, mixed> $device */
    private function writeData(array $device, bool $isUpdate): DeviceWriteData
    {
        return new DeviceWriteData(
            name: $device['name'],
            deviceType: $device['device_type'],
            enabled: $device['is_enabled'],
            ownerId: $device['owner_switch_resource_id'],
            make: $device['make'],
            model: $device['model'],
            macAddress: $device['mac_address'],
            sipUsername: $device['sip_username'],
            sipPassword: $device['sip_password'],
            family: $device['family'],
            callForward: $this->callForwardData($device),
            sip: $this->sipData($device),
            media: $this->mediaData($device),
            callerId: $this->callerIdData($device),
            advanced: $this->advancedData($device, $isUpdate),
            callRecording: $this->callRecordingData($device),
            musicOnHold: $this->musicOnHoldData($device),
            outboundFlags: $this->outboundFlagsData($device),
            dialPlan: $this->dialPlanData($device),
            metaflows: $this->metaflowsData($device),
        );
    }

    /** @param array<string, mixed> $device */
    private function callForwardData(array $device): ?DeviceCallForwardData
    {
        if (! isset($device['call_forward']) || ! is_array($device['call_forward'])) {
            return null;
        }

        $data = $device['call_forward'];

        return new DeviceCallForwardData(
            enabled: $data['enabled'] ?? null,
            number: $data['number'] ?? null,
            directCallsOnly: $data['direct_calls_only'] ?? null,
            failover: $data['failover'] ?? null,
            ignoreEarlyMedia: $data['ignore_early_media'] ?? null,
            keepCallerId: $data['keep_caller_id'] ?? null,
            requireKeypress: $data['require_keypress'] ?? null,
            substitute: $data['substitute'] ?? null,
        );
    }

    /** @param array<string, mixed> $device */
    private function sipData(array $device): ?DeviceSipData
    {
        if (! isset($device['sip']) || ! is_array($device['sip'])) {
            return null;
        }

        $data = $device['sip'];

        return new DeviceSipData(
            method: $data['method'] ?? null,
            username: $data['username'] ?? null,
            password: $data['password'] ?? null,
            realm: $data['realm'] ?? null,
            expireSeconds: $data['expire_seconds'] ?? null,
            inviteFormat: $data['invite_format'] ?? null,
            ip: $data['ip'] ?? null,
            number: $data['number'] ?? null,
            route: $data['route'] ?? null,
            staticRoute: $data['static_route'] ?? null,
            ignoreCompletedElsewhere: $data['ignore_completed_elsewhere'] ?? null,
            customSipHeaders: $this->customSipHeadersData($data['custom_sip_headers'] ?? null),
        );
    }

    private function customSipHeadersData(mixed $headers): ?DeviceCustomSipHeadersData
    {
        if (! is_array($headers)) {
            return null;
        }

        return new DeviceCustomSipHeadersData(
            inbound: $this->headerMap($headers['in'] ?? []),
            outbound: $this->headerMap($headers['out'] ?? []),
        );
    }

    /** @return array<string, string> */
    private function headerMap(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $headers = [];

        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['name'] ?? null) && is_string($row['value'] ?? null)) {
                $headers[$row['name']] = $row['value'];
            }
        }

        return $headers;
    }

    /** @param array<string, mixed> $device */
    private function musicOnHoldData(array $device): ?DeviceMusicOnHoldData
    {
        return array_key_exists('music_on_hold', $device)
            ? new DeviceMusicOnHoldData(Arr::get($device, 'music_on_hold.media_id'))
            : null;
    }

    /** @param array<string, mixed> $device */
    private function outboundFlagsData(array $device): ?DeviceOutboundFlagsData
    {
        $flags = $device['outbound_flags'] ?? null;

        return is_array($flags)
            ? new DeviceOutboundFlagsData($flags['static'] ?? [], $flags['dynamic'] ?? [])
            : null;
    }

    /** @param array<string, mixed> $device */
    private function dialPlanData(array $device): ?DeviceDialPlanData
    {
        $dialPlan = $device['dial_plan'] ?? null;

        return is_array($dialPlan)
            ? new DeviceDialPlanData($dialPlan['system'] ?? [], $dialPlan['rules'] ?? [])
            : null;
    }

    /** @param array<string, mixed> $device */
    private function metaflowsData(array $device): ?DeviceMetaflowsData
    {
        $metaflows = $device['metaflows'] ?? null;

        return is_array($metaflows)
            ? new DeviceMetaflowsData(
                bindingDigit: $metaflows['binding_digit'] ?? null,
                digitTimeout: $metaflows['digit_timeout'] ?? null,
                listenOn: $metaflows['listen_on'] ?? null,
            )
            : null;
    }

    /** @param array<string, mixed> $device */
    private function mediaData(array $device): ?DeviceMediaData
    {
        if (! isset($device['media']) || ! is_array($device['media'])) {
            return null;
        }

        $data = $device['media'];

        return new DeviceMediaData(
            audioCodecs: Arr::get($data, 'audio.codecs'),
            videoCodecs: Arr::get($data, 'video.codecs'),
            bypassMedia: $data['bypass_media'] ?? null,
            enforceEncryption: Arr::get($data, 'encryption.enforce_security'),
            encryptionMethods: Arr::get($data, 'encryption.methods'),
            faxOption: $data['fax_option'] ?? null,
            ignoreEarlyMedia: $data['ignore_early_media'] ?? null,
            progressTimeout: $data['progress_timeout'] ?? null,
        );
    }

    /** @param array<string, mixed> $device */
    private function callerIdData(array $device): ?DeviceCallerIdData
    {
        if (! isset($device['caller_id']) || ! is_array($device['caller_id'])) {
            return null;
        }

        $data = $device['caller_id'];

        return new DeviceCallerIdData(
            internalName: Arr::get($data, 'internal.name'),
            internalNumber: Arr::get($data, 'internal.number'),
            externalName: Arr::get($data, 'external.name'),
            externalNumber: Arr::get($data, 'external.number'),
            emergencyName: Arr::get($data, 'emergency.name'),
            emergencyNumber: Arr::get($data, 'emergency.number'),
            assertedName: Arr::get($data, 'asserted.name'),
            assertedNumber: Arr::get($data, 'asserted.number'),
            assertedRealm: Arr::get($data, 'asserted.realm'),
        );
    }

    /** @param array<string, mixed> $device */
    private function advancedData(array $device, bool $isUpdate): ?DeviceAdvancedData
    {
        $fields = [
            'language',
            'timezone',
            'presence_id',
            'do_not_disturb',
            'call_waiting',
            'exclude_from_queues',
            'contact_list',
            'mwi_unsolicited_updates',
            'register_overwrite_notify',
            'suppress_unregister_notifications',
            'caller_id_options',
            'ringtones',
            'call_restriction',
        ];

        if (! Arr::hasAny($device, $fields)) {
            return null;
        }

        return new DeviceAdvancedData(
            language: $this->clearableString($device, 'language', $isUpdate),
            timezone: $this->clearableString($device, 'timezone', $isUpdate),
            presenceId: $this->clearableString($device, 'presence_id', $isUpdate),
            doNotDisturb: Arr::get($device, 'do_not_disturb.enabled'),
            callWaiting: Arr::get($device, 'call_waiting.enabled'),
            excludeFromQueues: $device['exclude_from_queues'] ?? null,
            excludeFromContactList: Arr::get($device, 'contact_list.exclude'),
            mwiUnsolicitedUpdates: $device['mwi_unsolicited_updates'] ?? null,
            registerOverwriteNotify: $device['register_overwrite_notify'] ?? null,
            suppressUnregisterNotifications: $device['suppress_unregister_notifications'] ?? null,
            outboundPrivacy: Arr::get($device, 'caller_id_options.outbound_privacy'),
            internalRingtone: $this->clearableString($device, 'ringtones.internal', $isUpdate),
            externalRingtone: $this->clearableString($device, 'ringtones.external', $isUpdate),
            callRestrictions: $device['call_restriction'] ?? null,
        );
    }

    /** @param array<string, mixed> $device */
    private function clearableString(array $device, string $path, bool $isUpdate): ?string
    {
        $value = Arr::get($device, $path);

        if ($isUpdate && Arr::has($device, $path) && $value === null) {
            return '';
        }

        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $device */
    private function callRecordingData(array $device): ?DeviceCallRecordingData
    {
        if (! isset($device['call_recording']) || ! is_array($device['call_recording'])) {
            return null;
        }

        return new DeviceCallRecordingData(
            any: $this->recordingSourceData($device['call_recording']['any'] ?? null),
            inbound: $this->recordingSourceData($device['call_recording']['inbound'] ?? null),
            outbound: $this->recordingSourceData($device['call_recording']['outbound'] ?? null),
        );
    }

    private function recordingSourceData(mixed $source): ?DeviceRecordingSourceData
    {
        if (! is_array($source)) {
            return null;
        }

        return new DeviceRecordingSourceData(
            any: $this->recordingParametersData($source['any'] ?? null),
            onnet: $this->recordingParametersData($source['onnet'] ?? null),
            offnet: $this->recordingParametersData($source['offnet'] ?? null),
        );
    }

    private function recordingParametersData(mixed $parameters): ?DeviceRecordingParametersData
    {
        if (! is_array($parameters)) {
            return null;
        }

        return new DeviceRecordingParametersData(
            enabled: $parameters['enabled'] ?? null,
            format: $parameters['format'] ?? null,
            minimumSeconds: $parameters['record_min_sec'] ?? null,
            recordOnAnswer: $parameters['record_on_answer'] ?? null,
            recordOnBridge: $parameters['record_on_bridge'] ?? null,
            sampleRate: $parameters['record_sample_rate'] ?? null,
            timeLimit: $parameters['time_limit'] ?? null,
        );
    }
}
