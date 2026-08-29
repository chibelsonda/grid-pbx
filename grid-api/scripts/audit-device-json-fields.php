<?php

declare(strict_types=1);

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use GridPbx\Switch\Domains\Devices\DeviceResourceClient;
use GridPbx\Switch\Domains\Devices\Dto\DeviceAdvancedData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceCallerIdData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceCallForwardData;
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
use GridPbx\Switch\Domains\Devices\Dto\DeviceSipData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceWriteData;
use GridPbx\Switch\Domains\LineKeys\Dto\LineKeyWriteData;
use GridPbx\Switch\Domains\LineKeys\LineKeyResourceClient;
use GridPbx\Switch\Domains\Media\Dto\MediaWriteData;
use GridPbx\Switch\Domains\Media\MediaResourceClient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Arr;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$account = SwitchAccount::query()->firstOrFail();
$accountId = $account->switch_account_id;
$devices = $app->make(DeviceResourceClient::class);
$lineKeys = $app->make(LineKeyResourceClient::class);
$media = $app->make(MediaResourceClient::class);
$redactor = $app->make(RedactSensitiveSwitchData::class);
$deviceSchema = $devices->schema();
$stamp = gmdate('YmdHis');
$deviceTypes = [
    'sip_device',
    'cellphone',
    'smartphone',
    'softphone',
    'landline',
    'fax',
    'ata',
    'sip_uri',
];
$sipTypes = ['sip_device', 'smartphone', 'softphone', 'fax', 'ata', 'sip_uri'];
$forwardingTypes = ['cellphone', 'smartphone', 'landline'];
$provisioningTypes = ['sip_device', 'fax', 'ata'];
$restrictionTypes = ['sip_device', 'smartphone', 'softphone', 'fax', 'ata'];
$codecTypes = ['sip_device', 'softphone'];
$ringtoneTypes = ['sip_device', 'smartphone', 'softphone', 'fax', 'ata'];
$auditMedia = null;
$captures = [];
$inviteFormats = $deviceSchema->enum('sip.invite_format', ['contact']);
$defaultInviteFormat = in_array('strip_plus', $inviteFormats, true) ? 'strip_plus' : 'contact';
$callForwardNumberMaximum = $deviceSchema->maxLength('call_forward.number', 15);
$supportsTemplateId = $deviceSchema->supports('provision.id');
$supportsModelArray = in_array('array', $deviceSchema->types('provision.endpoint_model'), true);
$legacyProvisioningFields = [
    'check_sync_event' => $deviceSchema->supports('provision.check_sync_event'),
    'check_sync_reload' => $deviceSchema->supports('provision.check_sync_reload'),
    'check_sync_reboot' => $deviceSchema->supports('provision.check_sync_reboot'),
];
$sipCompatibilityFields = [
    'custom_sip_interface' => $deviceSchema->supports('sip.custom_sip_interface'),
    'forward' => $deviceSchema->supports('sip.forward'),
    'proxy' => $deviceSchema->supports('sip.proxy'),
    'static_invite' => $deviceSchema->supports('sip.static_invite'),
    'transport' => $deviceSchema->supports('sip.transport'),
];

$assertSame = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s Expected %s, received %s.',
            $message,
            json_encode($expected, JSON_THROW_ON_ERROR),
            json_encode($actual, JSON_THROW_ON_ERROR),
        ));
    }
};

$sipData = static function (string $deviceType, string $stage) use (
    $defaultInviteFormat,
    $sipCompatibilityFields,
    $stamp,
): DeviceSipData {
    $headers = $stage === 'clear'
        ? new DeviceCustomSipHeadersData
        : new DeviceCustomSipHeadersData(
            inbound: ['X-GridPBX-Audit-In' => $stage],
            outbound: ['X-GridPBX-Audit-Out' => $stage],
        );

    if ($deviceType === 'sip_uri') {
        return new DeviceSipData(
            method: 'password',
            username: "audit_{$stamp}",
            password: "audit-{$stamp}-secret",
            inviteFormat: 'route',
            route: 'sip:gridpbx-audit@example.invalid',
            customSipHeaders: $headers,
            customSipInterface: $sipCompatibilityFields['custom_sip_interface'] ? ($stage === 'clear' ? '' : "gridpbx-{$stage}") : null,
            forward: $sipCompatibilityFields['forward'] ? ($stage === 'clear' ? '' : '192.0.2.'.($stage === 'created' ? '10' : '11')) : null,
            proxy: $sipCompatibilityFields['proxy'] ? ($stage === 'clear' ? '' : "proxy-{$stage}.example.invalid") : null,
            staticInvite: $sipCompatibilityFields['static_invite'] ? ($stage === 'clear' ? '' : "audit-{$stage}") : null,
            transport: $sipCompatibilityFields['transport'] ? ($stage === 'clear' ? '' : ($stage === 'created' ? 'tcp' : 'tls')) : null,
        );
    }

    return new DeviceSipData(
        method: 'password',
        username: substr("audit_{$deviceType}_{$stamp}", 0, 32),
        password: "audit-{$stamp}-secret",
        inviteFormat: $defaultInviteFormat,
        customSipHeaders: $headers,
        customSipInterface: $sipCompatibilityFields['custom_sip_interface'] ? ($stage === 'clear' ? '' : "gridpbx-{$stage}") : null,
        forward: $sipCompatibilityFields['forward'] ? ($stage === 'clear' ? '' : '192.0.2.'.($stage === 'created' ? '10' : '11')) : null,
        proxy: $sipCompatibilityFields['proxy'] ? ($stage === 'clear' ? '' : "proxy-{$stage}.example.invalid") : null,
        staticInvite: $sipCompatibilityFields['static_invite'] ? ($stage === 'clear' ? '' : "audit-{$stage}") : null,
        transport: $sipCompatibilityFields['transport'] ? ($stage === 'clear' ? '' : ($stage === 'created' ? 'tcp' : 'tls')) : null,
    );
};

$provisioningData = static function (string $stage) use (
    $legacyProvisioningFields,
    $supportsTemplateId,
): DeviceProvisioningData {
    $value = static fn (string $created, string $edited): string => match ($stage) {
        'created' => $created,
        'edited' => $edited,
        default => '',
    };

    return new DeviceProvisioningData(
        checkSyncEvent: $legacyProvisioningFields['check_sync_event']
            ? $value('gridpbx-created', 'gridpbx-edited')
            : null,
        checkSyncReload: $legacyProvisioningFields['check_sync_reload']
            ? $value('gridpbx-reload-created', 'gridpbx-reload-edited')
            : null,
        checkSyncReboot: $legacyProvisioningFields['check_sync_reboot']
            ? $value('gridpbx-reboot-created', 'gridpbx-reboot-edited')
            : null,
        templateId: $supportsTemplateId
            ? $value('gridpbx-template-created', 'gridpbx-template-edited')
            : null,
    );
};

$selected = static fn (array $snapshot): array => Arr::only($snapshot, [
    'id',
    'name',
    'device_type',
    'call_forward',
    'music_on_hold',
    'outbound_flags',
    'sip',
    'media',
    'ringtones',
    'call_restriction',
    'dial_plan',
    'metaflows',
    'flags',
    'formatters',
    'provision',
]);

try {
    $auditMedia = $media->create($accountId, new MediaWriteData(
        name: "GridPBX Audit Music {$stamp}",
        description: 'Temporary media metadata used for Device schema verification.',
    ));

    foreach ($deviceTypes as $deviceType) {
        $deviceId = null;
        $usesSip = in_array($deviceType, $sipTypes, true);
        $usesForwarding = in_array($deviceType, $forwardingTypes, true);
        $supportsProvisioning = in_array($deviceType, $provisioningTypes, true);
        $supportsRestrictions = in_array($deviceType, $restrictionTypes, true);
        $supportsCodecs = in_array($deviceType, $codecTypes, true);
        $supportsRingtones = in_array($deviceType, $ringtoneTypes, true);

        try {
            $created = $devices->create($accountId, new DeviceWriteData(
                name: "GridPBX Audit {$deviceType} {$stamp}",
                deviceType: $deviceType,
                enabled: true,
                make: $supportsProvisioning ? 'yealink' : null,
                model: $supportsProvisioning
                    ? ($supportsModelArray && $deviceType === 'fax' ? ['t54w', 't54w-v2'] : 't54w')
                    : null,
                family: $supportsProvisioning ? 't5' : null,
                macAddress: $supportsProvisioning ? '02:00:00:00:00:'.match ($deviceType) {
                    'sip_device' => '01',
                    'fax' => '02',
                    'ata' => '03',
                } : null,
                sip: $usesSip ? $sipData($deviceType, 'created') : null,
                callForward: $usesForwarding ? new DeviceCallForwardData(
                    enabled: true,
                    number: $callForwardNumberMaximum >= 20 ? '+1555123456789012345' : '+15551234567',
                ) : null,
                media: $supportsCodecs ? new DeviceMediaData(
                    audioCodecs: ['OPUS', 'PCMU', 'PCMA'],
                    videoCodecs: ['VP8', 'H264'],
                ) : null,
                callerId: new DeviceCallerIdData(
                    internalName: 'GridPBX Internal',
                    internalNumber: '1001',
                    externalName: 'GridPBX External',
                    externalNumber: '+15551234567',
                    emergencyName: 'GridPBX Emergency',
                    emergencyNumber: '+15557654321',
                ),
                musicOnHold: new DeviceMusicOnHoldData($auditMedia->id),
                outboundFlags: new DeviceOutboundFlagsData(['gridpbx-created'], ['dynamic-created']),
                dialPlan: new DeviceDialPlanData(
                    system: ['north_america'],
                    rules: [[
                        'pattern' => '^91([0-9]{7})$',
                        'description' => 'GridPBX audit create rule',
                        'prefix' => '+1555',
                        'suffix' => null,
                    ]],
                ),
                metaflows: new DeviceMetaflowsData(
                    '*',
                    2200,
                    'both',
                    numbers: ['51' => [
                        'module' => 'transfer',
                        'data' => ['target' => '1001', 'transfer_type' => 'blind'],
                        'children' => (object) [],
                    ]],
                    patterns: [],
                ),
                flags: new DeviceFlagsData(['gridpbx-audit-created']),
                formatters: new DeviceFormattersData([
                    new DeviceFormatterRuleData(
                        field: 'request',
                        direction: 'outbound',
                        prefix: '+1',
                        regex: '^([0-9]+)$',
                    ),
                ]),
                provisioning: $supportsProvisioning
                    ? $provisioningData('created')
                    : null,
                advanced: $supportsRestrictions || $supportsRingtones
                    ? new DeviceAdvancedData(
                        internalRingtone: $supportsRingtones ? 'gridpbx-internal-created' : null,
                        externalRingtone: $supportsRingtones ? 'gridpbx-external-created' : null,
                        callRestrictions: $supportsRestrictions ? [
                            'closed_groups' => ['action' => 'deny'],
                            'international' => ['action' => 'deny'],
                        ] : null,
                    )
                    : null,
            ));
            $deviceId = $created->id;
            $createdRead = $devices->get($accountId, $deviceId)->toArray();
            $assertSame($auditMedia->id, Arr::get($createdRead, 'music_on_hold.media_id'), "{$deviceType} create music_on_hold mismatch.");
            $createFlags = Arr::get($createdRead, 'outbound_flags');
            if (! is_array($createFlags) || ! in_array('gridpbx-created', Arr::flatten($createFlags), true)) {
                throw new RuntimeException("{$deviceType} create outbound flags mismatch: ".json_encode($createFlags, JSON_THROW_ON_ERROR));
            }
            $assertSame('north_america', Arr::get($createdRead, 'dial_plan.system.0'), "{$deviceType} create dial plan mismatch.");
            $assertSame('*', Arr::get($createdRead, 'metaflows.binding_digit'), "{$deviceType} create metaflow mismatch.");
            $assertSame('1001', Arr::get($createdRead, 'metaflows.numbers.51.data.target'), "{$deviceType} create metaflow action mismatch.");
            $assertSame('+15551234567', Arr::get($createdRead, 'caller_id.external.number'), "{$deviceType} create caller ID mismatch.");
            $assertSame('gridpbx-audit-created', Arr::get($createdRead, 'flags.0'), "{$deviceType} create general flags mismatch.");
            $assertSame('+1', Arr::get($createdRead, 'formatters.request.0.prefix'), "{$deviceType} create formatter mismatch.");

            if ($supportsProvisioning) {
                $assertSame('yealink', Arr::get($createdRead, 'provision.endpoint_brand'), "{$deviceType} create provisioning brand mismatch.");
                $assertSame('t5', Arr::get($createdRead, 'provision.endpoint_family'), "{$deviceType} create provisioning family mismatch.");
                $expectedModel = $supportsModelArray && $deviceType === 'fax' ? ['t54w', 't54w-v2'] : 't54w';
                $assertSame($expectedModel, Arr::get($createdRead, 'provision.endpoint_model'), "{$deviceType} create provisioning model mismatch.");

                if ($legacyProvisioningFields['check_sync_event']) {
                    $assertSame('gridpbx-created', Arr::get($createdRead, 'provision.check_sync_event'), "{$deviceType} create provisioning event mismatch.");
                }

                if ($supportsTemplateId) {
                    $assertSame('gridpbx-template-created', Arr::get($createdRead, 'provision.id'), "{$deviceType} create provisioning template mismatch.");
                }
            }

            if ($usesForwarding) {
                $expectedNumber = $callForwardNumberMaximum >= 20 ? '+1555123456789012345' : '+15551234567';
                $assertSame($expectedNumber, Arr::get($createdRead, 'call_forward.number'), "{$deviceType} create call-forward number mismatch.");
            }

            if ($supportsCodecs) {
                $assertSame(['OPUS', 'PCMU', 'PCMA'], Arr::get($createdRead, 'media.audio.codecs'), "{$deviceType} create audio codec order mismatch.");
                $assertSame(['VP8', 'H264'], Arr::get($createdRead, 'media.video.codecs'), "{$deviceType} create video codec order mismatch.");
            }

            if ($supportsRingtones) {
                $assertSame('gridpbx-internal-created', Arr::get($createdRead, 'ringtones.internal'), "{$deviceType} create internal ringtone mismatch.");
                $assertSame('gridpbx-external-created', Arr::get($createdRead, 'ringtones.external'), "{$deviceType} create external ringtone mismatch.");
            }

            if ($supportsRestrictions) {
                $assertSame('deny', Arr::get($createdRead, 'call_restriction.closed_groups.action'), "{$deviceType} create closed-group restriction mismatch.");
                $assertSame('deny', Arr::get($createdRead, 'call_restriction.international.action'), "{$deviceType} create international restriction mismatch.");
            }

            if ($deviceType === 'sip_device') {
                $lineKeySnapshot = $lineKeys->update($accountId, $deviceId, [
                    new LineKeyWriteData('combo', 0, 'line', '1001', 'Line 1'),
                    new LineKeyWriteData('combo', 1, 'presence', '1002', 'Reception BLF'),
                    new LineKeyWriteData('feature', 0, 'personal_parking', '1003', 'My parking'),
                    new LineKeyWriteData('feature', 1, 'speed_dial', '+15551234567', 'Support'),
                    new LineKeyWriteData('feature', 2, 'parking', 3, 'Parking 3'),
                ]);
                $assertSame(5, count($lineKeySnapshot->lineKeys), 'sip_device line-key type matrix mismatch.');

                $lineKeySnapshot = $lineKeys->update($accountId, $deviceId, [
                    new LineKeyWriteData('feature', 4, 'speed_dial', '1004', 'Edited'),
                ]);
                $assertSame(1, count($lineKeySnapshot->lineKeys), 'sip_device line-key replacement mismatch.');

                $lineKeySnapshot = $lineKeys->update($accountId, $deviceId, []);
                $assertSame(0, count($lineKeySnapshot->lineKeys), 'sip_device line keys were not cleared.');
            }

            if ($usesSip) {
                $assertSame('created', Arr::get($createdRead, 'sip.custom_sip_headers.in.X-GridPBX-Audit-In'), "{$deviceType} create SIP header mismatch.");
            }

            $updated = $devices->update($accountId, $deviceId, new DeviceWriteData(
                name: "GridPBX Audit {$deviceType} Edited {$stamp}",
                deviceType: $deviceType,
                enabled: true,
                sip: $usesSip ? $sipData($deviceType, 'edited') : null,
                callForward: $usesForwarding ? new DeviceCallForwardData(
                    enabled: true,
                    number: $callForwardNumberMaximum >= 20 ? '+1555987654321098765' : '+15557654321',
                ) : null,
                media: $supportsCodecs ? new DeviceMediaData(
                    audioCodecs: ['PCMA', 'OPUS'],
                    videoCodecs: ['H264', 'VP8'],
                ) : null,
                callerId: new DeviceCallerIdData(
                    internalName: 'GridPBX Internal Edited',
                    internalNumber: '1002',
                    externalName: 'GridPBX External Edited',
                    externalNumber: '+15551234568',
                    emergencyName: 'GridPBX Emergency Edited',
                    emergencyNumber: '+15557654322',
                ),
                musicOnHold: new DeviceMusicOnHoldData($auditMedia->id),
                outboundFlags: new DeviceOutboundFlagsData(['gridpbx-edited'], ['dynamic-edited']),
                dialPlan: new DeviceDialPlanData(
                    system: ['north_america'],
                    rules: [[
                        'pattern' => '^81([0-9]{7})$',
                        'description' => 'GridPBX audit edit rule',
                        'prefix' => '+1444',
                        'suffix' => '#',
                    ]],
                ),
                metaflows: new DeviceMetaflowsData(
                    '#',
                    1800,
                    'self',
                    numbers: [],
                    patterns: ['^52([0-9]+)$' => [
                        'module' => 'hangup',
                        'data' => (object) [],
                        'children' => (object) [],
                    ]],
                ),
                flags: new DeviceFlagsData(['gridpbx-audit-edited']),
                formatters: new DeviceFormattersData([
                    new DeviceFormatterRuleData(
                        field: 'request',
                        direction: 'inbound',
                        suffix: '#',
                        value: 'audit-edited',
                    ),
                ]),
                provisioning: $supportsProvisioning
                    ? $provisioningData('edited')
                    : null,
                advanced: $supportsRestrictions || $supportsRingtones
                    ? new DeviceAdvancedData(
                        internalRingtone: $supportsRingtones ? 'gridpbx-internal-edited' : null,
                        externalRingtone: $supportsRingtones ? 'gridpbx-external-edited' : null,
                        callRestrictions: $supportsRestrictions ? [
                            'closed_groups' => ['action' => 'inherit'],
                            'international' => ['action' => 'inherit'],
                            'unknown' => ['action' => 'deny'],
                        ] : null,
                    )
                    : null,
            ));
            $updatedRead = $devices->get($accountId, $deviceId)->toArray();
            $editFlags = Arr::get($updatedRead, 'outbound_flags');
            if (! is_array($editFlags) || ! in_array('gridpbx-edited', Arr::flatten($editFlags), true)) {
                throw new RuntimeException("{$deviceType} edit outbound flags mismatch: ".json_encode($editFlags, JSON_THROW_ON_ERROR));
            }
            $assertSame(null, Arr::get($updatedRead, 'dial_plan.^91([0-9]{7})$'), "{$deviceType} old dial plan rule was not replaced.");
            $assertSame('+1444', Arr::get($updatedRead, 'dial_plan.^81([0-9]{7})$.prefix'), "{$deviceType} edit dial plan mismatch.");
            $assertSame('#', Arr::get($updatedRead, 'metaflows.binding_digit'), "{$deviceType} edit metaflow mismatch.");
            $assertSame(null, Arr::get($updatedRead, 'metaflows.numbers.51'), "{$deviceType} old metaflow action was not replaced.");
            $assertSame('hangup', Arr::get($updatedRead, 'metaflows.patterns.^52([0-9]+)$.module'), "{$deviceType} edit metaflow action mismatch.");
            $assertSame('+15551234568', Arr::get($updatedRead, 'caller_id.external.number'), "{$deviceType} edit caller ID mismatch.");
            $assertSame('gridpbx-audit-edited', Arr::get($updatedRead, 'flags.0'), "{$deviceType} edit general flags mismatch.");
            $assertSame(null, Arr::get($updatedRead, 'formatters.request.0.prefix'), "{$deviceType} old formatter options were not replaced.");
            $assertSame('#', Arr::get($updatedRead, 'formatters.request.0.suffix'), "{$deviceType} edit formatter mismatch.");

            if ($supportsProvisioning) {
                if ($legacyProvisioningFields['check_sync_event']) {
                    $assertSame('gridpbx-edited', Arr::get($updatedRead, 'provision.check_sync_event'), "{$deviceType} edit provisioning event mismatch.");
                }

                if ($supportsTemplateId) {
                    $assertSame('gridpbx-template-edited', Arr::get($updatedRead, 'provision.id'), "{$deviceType} edit provisioning template mismatch.");
                }
                $devices->sync($accountId, $deviceId, false);
                $devices->sync($accountId, $deviceId, true);
            }

            if ($usesForwarding) {
                $expectedNumber = $callForwardNumberMaximum >= 20 ? '+1555987654321098765' : '+15557654321';
                $assertSame($expectedNumber, Arr::get($updatedRead, 'call_forward.number'), "{$deviceType} edit call-forward number mismatch.");
            }

            if ($supportsCodecs) {
                $assertSame(['PCMA', 'OPUS'], Arr::get($updatedRead, 'media.audio.codecs'), "{$deviceType} edit audio codec order mismatch.");
                $assertSame(['H264', 'VP8'], Arr::get($updatedRead, 'media.video.codecs'), "{$deviceType} edit video codec order mismatch.");
            }

            if ($supportsRingtones) {
                $assertSame('gridpbx-internal-edited', Arr::get($updatedRead, 'ringtones.internal'), "{$deviceType} edit internal ringtone mismatch.");
                $assertSame('gridpbx-external-edited', Arr::get($updatedRead, 'ringtones.external'), "{$deviceType} edit external ringtone mismatch.");
            }

            if ($supportsRestrictions) {
                $assertSame('inherit', Arr::get($updatedRead, 'call_restriction.closed_groups.action'), "{$deviceType} edit closed-group restriction mismatch.");
                $assertSame('inherit', Arr::get($updatedRead, 'call_restriction.international.action'), "{$deviceType} edit international restriction mismatch.");
                $assertSame('deny', Arr::get($updatedRead, 'call_restriction.unknown.action'), "{$deviceType} edit unknown restriction mismatch.");
            }

            if ($usesSip) {
                $assertSame('edited', Arr::get($updatedRead, 'sip.custom_sip_headers.out.X-GridPBX-Audit-Out'), "{$deviceType} edit SIP header mismatch.");

                foreach ($sipCompatibilityFields as $field => $supported) {
                    if ($supported) {
                        $assertSame(
                            match ($field) {
                                'custom_sip_interface' => 'gridpbx-edited',
                                'forward' => '192.0.2.11',
                                'proxy' => 'proxy-edited.example.invalid',
                                'static_invite' => 'audit-edited',
                                'transport' => 'tls',
                            },
                            Arr::get($updatedRead, "sip.{$field}"),
                            "{$deviceType} edit {$field} mismatch.",
                        );
                    }
                }
            }

            $cleared = $devices->update($accountId, $deviceId, new DeviceWriteData(
                name: "GridPBX Audit {$deviceType} Cleared {$stamp}",
                deviceType: $deviceType,
                enabled: true,
                sip: $usesSip ? $sipData($deviceType, 'clear') : null,
                callForward: $usesForwarding ? new DeviceCallForwardData(enabled: false, number: '') : null,
                media: $supportsCodecs ? new DeviceMediaData(audioCodecs: [], videoCodecs: []) : null,
                callerId: new DeviceCallerIdData('', '', '', '', '', '', '', '', ''),
                musicOnHold: new DeviceMusicOnHoldData,
                outboundFlags: new DeviceOutboundFlagsData,
                dialPlan: new DeviceDialPlanData,
                metaflows: new DeviceMetaflowsData(numbers: [], patterns: []),
                flags: new DeviceFlagsData,
                formatters: new DeviceFormattersData,
                provisioning: $supportsProvisioning ? $provisioningData('clear') : null,
                advanced: $supportsRestrictions || $supportsRingtones
                    ? new DeviceAdvancedData(
                        internalRingtone: $supportsRingtones ? '' : null,
                        externalRingtone: $supportsRingtones ? '' : null,
                        callRestrictions: $supportsRestrictions ? [
                            'closed_groups' => ['action' => 'inherit'],
                            'international' => ['action' => 'inherit'],
                            'unknown' => ['action' => 'inherit'],
                        ] : null,
                    )
                    : null,
            ));
            $clearedRead = $devices->get($accountId, $deviceId)->toArray();
            $assertSame(null, Arr::get($clearedRead, 'music_on_hold.media_id'), "{$deviceType} music_on_hold was not cleared.");
            $clearFlags = Arr::get($clearedRead, 'outbound_flags', []);
            $assertSame([], is_array($clearFlags) ? Arr::flatten($clearFlags) : [], "{$deviceType} outbound flags were not cleared.");
            $assertSame(null, Arr::get($clearedRead, 'dial_plan.^81([0-9]{7})$'), "{$deviceType} dial plan was not cleared.");
            $assertSame('*', Arr::get($clearedRead, 'metaflows.binding_digit'), "{$deviceType} metaflow binding digit did not reset to the Switch default.");
            $assertSame(null, Arr::get($clearedRead, 'metaflows.digit_timeout'), "{$deviceType} metaflow timeout was not cleared.");
            $assertSame(null, Arr::get($clearedRead, 'metaflows.listen_on'), "{$deviceType} metaflow listen-on value was not cleared.");
            $assertSame(null, Arr::get($clearedRead, 'metaflows.patterns.^52([0-9]+)$'), "{$deviceType} metaflow actions were not cleared.");
            $assertSame('', Arr::get($clearedRead, 'caller_id.external.number'), "{$deviceType} caller ID was not cleared.");
            $assertSame([], Arr::get($clearedRead, 'flags', []), "{$deviceType} general flags were not cleared.");
            $assertSame(null, Arr::get($clearedRead, 'formatters.request'), "{$deviceType} formatter was not cleared.");

            if ($supportsProvisioning) {
                // This Switch represents a cleared optional provisioning string as "".
                // DeviceResource normalizes that wire value back to null for the UI.
                foreach ($legacyProvisioningFields as $field => $supported) {
                    if ($supported) {
                        $assertSame('', Arr::get($clearedRead, "provision.{$field}"), "{$deviceType} {$field} was not cleared.");
                    }
                }

                if ($supportsTemplateId) {
                    $assertSame('', Arr::get($clearedRead, 'provision.id'), "{$deviceType} provisioning template was not cleared.");
                }
            }

            if ($usesForwarding) {
                $assertSame('', Arr::get($clearedRead, 'call_forward.number'), "{$deviceType} call-forward number was not cleared.");
            }

            if ($supportsCodecs) {
                $assertSame([], Arr::get($clearedRead, 'media.audio.codecs'), "{$deviceType} audio codecs were not cleared.");
                $assertSame([], Arr::get($clearedRead, 'media.video.codecs'), "{$deviceType} video codecs were not cleared.");
            }

            if ($supportsRingtones) {
                $assertSame('', Arr::get($clearedRead, 'ringtones.internal'), "{$deviceType} internal ringtone was not cleared.");
                $assertSame('', Arr::get($clearedRead, 'ringtones.external'), "{$deviceType} external ringtone was not cleared.");
            }

            if ($supportsRestrictions) {
                $assertSame('inherit', Arr::get($clearedRead, 'call_restriction.closed_groups.action'), "{$deviceType} closed-group restriction was not reset.");
                $assertSame('inherit', Arr::get($clearedRead, 'call_restriction.international.action'), "{$deviceType} international restriction was not reset.");
                $assertSame('inherit', Arr::get($clearedRead, 'call_restriction.unknown.action'), "{$deviceType} unknown restriction was not reset.");
            }

            if ($usesSip) {
                $assertSame(null, Arr::get($clearedRead, 'sip.custom_sip_headers.out.X-GridPBX-Audit-Out'), "{$deviceType} SIP headers were not cleared.");

                foreach ($sipCompatibilityFields as $field => $supported) {
                    if ($supported) {
                        $assertSame('', Arr::get($clearedRead, "sip.{$field}"), "{$deviceType} {$field} was not cleared.");
                    }
                }
            }

            $captures[$deviceType] = [
                'create_response' => $selected($created->toArray()),
                'create_read' => $selected($createdRead),
                'edit_response' => $selected($updated->toArray()),
                'edit_read' => $selected($updatedRead),
                'clear_response' => $selected($cleared->toArray()),
                'clear_read' => $selected($clearedRead),
            ];
            fwrite(STDOUT, "verified {$deviceType}\n");
        } finally {
            if (is_string($deviceId)) {
                $devices->delete($accountId, $deviceId);
            }
        }
    }

    $fixture = [
        'captured_at' => gmdate(DATE_ATOM),
        'purpose' => 'Live create/edit/clear verification for JSON-backed Device routing, restrictions, codec order, ringtones, flags, formatters, and provisioning fields.',
        'temporary_resources_removed' => true,
        'connected_schema' => [
            'id' => $deviceSchema->id(),
            'call_forward_number_max_length' => $callForwardNumberMaximum,
            'invite_formats' => $inviteFormats,
            'sip_fields' => $sipCompatibilityFields,
            'provision_template_id' => $supportsTemplateId,
            'provision_endpoint_model_array' => $supportsModelArray,
            'legacy_provisioning_fields' => $legacyProvisioningFields,
        ],
        'device_types' => $redactor->handle($captures),
    ];
    $fixturePath = '/grid-api-switch/tests/Fixtures/Devices/runtime-routing-fields.json';
    file_put_contents(
        $fixturePath,
        json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
    );
    fwrite(STDOUT, "fixture {$fixturePath}\n");
} finally {
    if ($auditMedia !== null) {
        $media->delete($accountId, $auditMedia->id);
    }
}
