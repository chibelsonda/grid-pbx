<?php

namespace App\Domains\Devices\Gateways;

use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Services\DeviceMetaflowPolicy;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Devices\DeviceResourceClient;
use GridPbx\Switch\Domains\Devices\Dto\DeviceAdvancedData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceCallerIdData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceCallForwardData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceCallRecordingData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceCustomSipHeadersData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceDialPlanData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceFlagsData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceFormatterRuleData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceFormattersData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceMediaData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceMetaflowsData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceMusicOnHoldData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceOutboundFlagsData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceProvisioningData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceRecordingParametersData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceRecordingSourceData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceSipData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceWriteData;
use GridPbx\Switch\Domains\PhoneNumbers\Dto\NumberClassifierSnapshot;
use GridPbx\Switch\Domains\PhoneNumbers\PhoneNumberResourceClient;
use Illuminate\Support\Arr;

class CrossbarSwitchDeviceGateway implements SwitchDeviceGateway
{
    public function __construct(
        private readonly DeviceResourceClient $devices,
        private readonly PhoneNumberResourceClient $phoneNumbers,
        private readonly DeviceMetaflowPolicy $metaflowPolicy,
    ) {}

    /** @return array<string, mixed> */
    public function schemaCompatibility(): array
    {
        $schema = $this->devices->schema();

        return [
            'source' => 'connected_switch',
            'schema_id' => $schema->id(),
            'call_forward' => [
                'number_max_length' => $schema->maxLength('call_forward.number', 15),
            ],
            'sip' => [
                'invite_formats' => $schema->enum('sip.invite_format', [
                    'username', 'npan', '1npan', 'e164', 'route', 'contact',
                ]),
                'custom_sip_interface' => $schema->supports('sip.custom_sip_interface'),
                'forward' => $schema->supports('sip.forward'),
                'proxy' => $schema->supports('sip.proxy'),
                'static_invite' => $schema->supports('sip.static_invite'),
                'transport' => $schema->supports('sip.transport'),
            ],
            'provision' => [
                'template_id' => $schema->supports('provision.id'),
                'endpoint_model_types' => $schema->types('provision.endpoint_model', ['string']),
                'check_sync_event' => $schema->supports('provision.check_sync_event'),
                'check_sync_reload' => $schema->supports('provision.check_sync_reload'),
                'check_sync_reboot' => $schema->supports('provision.check_sync_reboot'),
            ],
        ];
    }

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
        $device = $this->prepareMetaflows($account, $device, []);

        return $this->devices
            ->create($account->switch_account_id, $this->writeData($device, false))
            ->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $device): array
    {
        $current = null;

        if (isset($device['metaflows']['actions']) && is_array($device['metaflows']['actions'])) {
            $current = $this->devices->get($account->switch_account_id, $resourceId)->toArray();
            $device = $this->prepareMetaflows($account, $device, $current['metaflows'] ?? []);
        }

        return $this->devices
            ->update($account->switch_account_id, $resourceId, $this->writeData($device, true), $current)
            ->toArray();
    }

    /** @param array<string, mixed> $device @param array<string, mixed> $currentMetaflows */
    private function prepareMetaflows(
        SwitchAccount $account,
        array $device,
        array $currentMetaflows,
    ): array {
        if (! isset($device['metaflows']['actions']) || ! is_array($device['metaflows']['actions'])) {
            return $device;
        }

        $maps = $this->metaflowPolicy->merge($currentMetaflows, $device['metaflows']['actions'], $account);
        $device['metaflows']['numbers'] = $maps['numbers'];
        $device['metaflows']['patterns'] = $maps['patterns'];
        unset($device['metaflows']['actions']);

        return $device;
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->devices->delete($account->switch_account_id, $resourceId);
    }

    public function sync(SwitchAccount $account, string $resourceId, bool $reboot): void
    {
        $this->devices->sync($account->switch_account_id, $resourceId, $reboot);
    }

    public function addHotdeskUser(SwitchAccount $account, string $resourceId, string $userResourceId): array
    {
        return $this->devices
            ->addHotdeskUser($account->switch_account_id, $resourceId, $userResourceId)
            ->toArray();
    }

    public function removeHotdeskUser(SwitchAccount $account, string $resourceId, string $userResourceId): array
    {
        return $this->devices
            ->removeHotdeskUser($account->switch_account_id, $resourceId, $userResourceId)
            ->toArray();
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
            advanced: $this->advancedData($device),
            callRecording: $this->callRecordingData($device),
            musicOnHold: $this->musicOnHoldData($device),
            outboundFlags: $this->outboundFlagsData($device),
            dialPlan: $this->dialPlanData($device),
            metaflows: $this->metaflowsData($device),
            flags: $this->flagsData($device),
            formatters: $this->formattersData($device),
            provisioning: $this->provisioningData($device),
            clearMissingProvisioningFields: $isUpdate,
            explicitPathsToClear: $this->explicitPathsToClear($device, $isUpdate),
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
            realm: $this->nullableString($data, 'realm'),
            expireSeconds: $data['expire_seconds'] ?? null,
            inviteFormat: $data['invite_format'] ?? null,
            ip: $data['ip'] ?? null,
            number: $this->nullableString($data, 'number'),
            route: $this->nullableString($data, 'route'),
            staticRoute: $this->nullableString($data, 'static_route'),
            ignoreCompletedElsewhere: $data['ignore_completed_elsewhere'] ?? null,
            customSipHeaders: $this->customSipHeadersData($data['custom_sip_headers'] ?? null),
            customSipInterface: $this->nullableString($data, 'custom_sip_interface'),
            forward: $this->nullableString($data, 'forward'),
            proxy: $this->nullableString($data, 'proxy'),
            staticInvite: $this->nullableString($data, 'static_invite'),
            transport: $this->nullableString($data, 'transport'),
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
                numbers: $metaflows['numbers'] ?? null,
                patterns: $metaflows['patterns'] ?? null,
            )
            : null;
    }

    /** @param  array<string, mixed>  $device */
    private function flagsData(array $device): ?DeviceFlagsData
    {
        return isset($device['flags']) && is_array($device['flags'])
            ? new DeviceFlagsData($device['flags'])
            : null;
    }

    /** @param  array<string, mixed>  $device */
    private function formattersData(array $device): ?DeviceFormattersData
    {
        $formatters = $device['formatters'] ?? null;

        if (! is_array($formatters)) {
            return null;
        }

        return new DeviceFormattersData(array_map(
            static fn (array $formatter): DeviceFormatterRuleData => new DeviceFormatterRuleData(
                field: $formatter['field'],
                direction: $formatter['direction'] ?? null,
                matchInviteFormat: $formatter['match_invite_format'] ?? null,
                prefix: $formatter['prefix'] ?? null,
                regex: $formatter['regex'] ?? null,
                strip: $formatter['strip'] ?? null,
                suffix: $formatter['suffix'] ?? null,
                value: $formatter['value'] ?? null,
            ),
            $formatters,
        ));
    }

    /** @param  array<string, mixed>  $device */
    private function provisioningData(array $device): ?DeviceProvisioningData
    {
        if (! isset($device['provision']) || ! is_array($device['provision'])) {
            return null;
        }

        return new DeviceProvisioningData(
            templateId: $this->nullableString($device, 'provision.id'),
            checkSyncEvent: $this->nullableString($device, 'provision.check_sync_event'),
            checkSyncReload: $this->nullableString($device, 'provision.check_sync_reload'),
            checkSyncReboot: $this->nullableString($device, 'provision.check_sync_reboot'),
        );
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
            internalName: $this->nullableString($data, 'internal.name'),
            internalNumber: $this->nullableString($data, 'internal.number'),
            externalName: $this->nullableString($data, 'external.name'),
            externalNumber: $this->nullableString($data, 'external.number'),
            emergencyName: $this->nullableString($data, 'emergency.name'),
            emergencyNumber: $this->nullableString($data, 'emergency.number'),
            assertedName: $this->nullableString($data, 'asserted.name'),
            assertedNumber: $this->nullableString($data, 'asserted.number'),
            assertedRealm: $this->nullableString($data, 'asserted.realm'),
        );
    }

    /** @param array<string, mixed> $device */
    private function advancedData(array $device): ?DeviceAdvancedData
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
            language: $this->nullableString($device, 'language'),
            timezone: $this->nullableString($device, 'timezone'),
            presenceId: $this->nullableString($device, 'presence_id'),
            doNotDisturb: Arr::get($device, 'do_not_disturb.enabled'),
            callWaiting: Arr::get($device, 'call_waiting.enabled'),
            excludeFromQueues: $device['exclude_from_queues'] ?? null,
            excludeFromContactList: Arr::get($device, 'contact_list.exclude'),
            mwiUnsolicitedUpdates: $device['mwi_unsolicited_updates'] ?? null,
            registerOverwriteNotify: $device['register_overwrite_notify'] ?? null,
            suppressUnregisterNotifications: $device['suppress_unregister_notifications'] ?? null,
            outboundPrivacy: Arr::get($device, 'caller_id_options.outbound_privacy'),
            internalRingtone: $this->nullableString($device, 'ringtones.internal'),
            externalRingtone: $this->nullableString($device, 'ringtones.external'),
            callRestrictions: $device['call_restriction'] ?? null,
        );
    }

    /** @param array<string, mixed> $device */
    private function nullableString(array $device, string $path): ?string
    {
        $value = Arr::get($device, $path);

        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $device @return list<string> */
    private function explicitPathsToClear(array $device, bool $isUpdate): array
    {
        if (! $isUpdate) {
            return [];
        }

        $paths = [
            'sip.realm',
            'sip.number',
            'sip.route',
            'sip.static_route',
            'sip.custom_sip_interface',
            'sip.forward',
            'sip.proxy',
            'sip.static_invite',
            'sip.transport',
            'caller_id.internal.name',
            'caller_id.internal.number',
            'caller_id.external.name',
            'caller_id.external.number',
            'caller_id.emergency.name',
            'caller_id.emergency.number',
            'caller_id.asserted.name',
            'caller_id.asserted.number',
            'caller_id.asserted.realm',
            'language',
            'timezone',
            'presence_id',
            'ringtones.internal',
            'ringtones.external',
            'provision.id',
            'provision.check_sync_event',
            'provision.check_sync_reload',
            'provision.check_sync_reboot',
        ];

        return array_values(array_filter(
            $paths,
            static fn (string $path): bool => Arr::has($device, $path)
                && Arr::get($device, $path) === null,
        ));
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
