<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

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
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class DeviceResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_it_reads_a_device_detail_as_a_typed_snapshot(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'call_restriction' => [
                    'international' => ['action' => 'deny'],
                ],
            ]]),
        ]);

        $snapshot = $client->get('account-1', 'device-1');

        self::assertSame('device-1', $snapshot->id);
        self::assertSame('Reception', $snapshot->name);
        self::assertSame('deny', $snapshot->toArray()['call_restriction']['international']['action']);
        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame(
            '/v2/accounts/account-1/devices/device-1',
            $this->history[0]['request']->getUri()->getPath(),
        );
    }

    public function test_it_reads_connected_device_schema_capabilities(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'devices',
                'properties' => [
                    'call_forward' => ['properties' => [
                        'number' => ['type' => 'string', 'maxLength' => 35],
                    ]],
                    'sip' => ['properties' => [
                        'invite_format' => ['type' => 'string', 'enum' => ['username', 'strip_plus']],
                        'proxy' => ['type' => 'string'],
                    ]],
                    'provision' => ['properties' => [
                        'endpoint_model' => ['oneOf' => [['type' => 'string'], ['type' => 'array']]],
                    ]],
                ],
            ]]),
        ]);

        $schema = $client->schema();

        self::assertSame('devices', $schema->id());
        self::assertSame(35, $schema->maxLength('call_forward.number', 15));
        self::assertSame(['username', 'strip_plus'], $schema->enum('sip.invite_format'));
        self::assertTrue($schema->supports('sip.proxy'));
        self::assertFalse($schema->supports('sip.forward'));
        self::assertSame(['string', 'array'], $schema->types('provision.endpoint_model'));
        self::assertSame('/v2/schemas/devices', $this->history[0]['request']->getUri()->getPath());
    }

    public function test_it_adds_a_hotdesk_user_while_preserving_the_live_device_document(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'sip' => ['username' => 'reception'],
                'call_restriction' => [],
                'hotdesk' => ['users' => ['user-1' => ['keep_logged_in_elsewhere' => true]]],
            ]]),
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'hotdesk' => ['users' => ['user-1' => [], 'user-2' => []]],
            ]]),
        ]);

        $snapshot = $client->addHotdeskUser('account-1', 'device-1', 'user-2');

        self::assertSame('device-1', $snapshot->id);
        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('POST', $this->history[1]['request']->getMethod());
        $payload = json_decode(
            (string) $this->history[1]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['data'];
        self::assertSame('reception', $payload['sip']['username']);
        self::assertSame(['user-1', 'user-2'], array_keys($payload['hotdesk']['users']));
        self::assertTrue($payload['hotdesk']['users']['user-1']['keep_logged_in_elsewhere']);
        self::assertStringContainsString('"user-2":{}', (string) $this->history[1]['request']->getBody());
        self::assertArrayNotHasKey('id', $payload);
        self::assertArrayNotHasKey('call_restriction', $payload);
    }

    public function test_it_removes_the_last_hotdesk_user_as_an_empty_switch_object(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'hotdesk' => ['users' => ['user-1' => []]],
            ]]),
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'hotdesk' => ['users' => []],
            ]]),
        ]);

        $client->removeHotdeskUser('account-1', 'device-1', 'user-1');

        $json = (string) $this->history[1]['request']->getBody();
        self::assertStringContainsString('"users":{}', $json);
    }

    public function test_it_creates_a_device_with_a_bounded_switch_payload(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
            ]]),
        ]);

        $snapshot = $client->create('account-1', new DeviceWriteData(
            name: 'Reception',
            deviceType: 'sip_device',
            enabled: true,
            ownerId: 'user-1',
            make: 'Yealink',
            model: 'T54W',
            macAddress: '00:11:22:33:44:55',
            sipUsername: 'reception',
            sipPassword: 'a-long-random-secret',
        ));

        self::assertSame('device-1', $snapshot->id);
        self::assertSame('PUT', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/devices', $this->history[0]['request']->getUri()->getPath());
        self::assertSame([
            'data' => [
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'owner_id' => 'user-1',
                'mac_address' => '00:11:22:33:44:55',
                'provision' => [
                    'endpoint_brand' => 'Yealink',
                    'endpoint_model' => 'T54W',
                ],
                'sip' => [
                    'username' => 'reception',
                    'password' => 'a-long-random-secret',
                ],
            ],
        ], json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_it_omits_an_empty_caller_id_object_from_a_device_payload(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Testing',
                'device_type' => 'sip_device',
                'enabled' => true,
            ]]),
        ]);

        $client->create('account-1', new DeviceWriteData(
            name: 'Testing',
            deviceType: 'sip_device',
            enabled: true,
            callerId: new DeviceCallerIdData,
        ));

        self::assertSame([
            'data' => [
                'name' => 'Testing',
                'device_type' => 'sip_device',
                'enabled' => true,
            ],
        ], json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_it_updates_a_device_without_deleting_unmanaged_fields_or_credentials(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'owner_id' => 'user-1',
                'sip' => [
                    'username' => 'reception',
                    'password' => 'existing-secret',
                    'invite_format' => 'contact',
                ],
                'media' => ['audio' => ['codecs' => ['OPUS', 'PCMU', 'PCMA']]],
                'metaflows' => ['binding_digit' => '*'],
                'music_on_hold' => [],
            ]]),
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Shared Phone',
                'device_type' => 'sip_device',
                'enabled' => false,
            ]]),
        ]);

        $client->update('account-1', 'device-1', new DeviceWriteData(
            name: 'Shared Phone',
            deviceType: 'sip_device',
            enabled: false,
        ));

        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('POST', $this->history[1]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/devices/device-1', $this->history[1]['request']->getUri()->getPath());
        self::assertSame([
            'data' => [
                'name' => 'Shared Phone',
                'device_type' => 'sip_device',
                'enabled' => false,
                'sip' => [
                    'username' => 'reception',
                    'password' => 'existing-secret',
                    'invite_format' => 'contact',
                ],
                'media' => ['audio' => ['codecs' => ['OPUS', 'PCMU', 'PCMA']]],
                'metaflows' => ['binding_digit' => '*'],
            ],
        ], json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_it_clears_managed_device_maps_and_preserves_unmanaged_metaflow_actions(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'music_on_hold' => ['media_id' => 'media-1'],
                'outbound_flags' => ['fax', 'regional'],
                'sip' => ['custom_sip_headers' => ['out' => ['X-Device' => 'reception']]],
                'dial_plan' => ['system' => ['north_america'], '^7(.*)$' => ['prefix' => '+1555']],
                'metaflows' => [
                    'binding_digit' => '*',
                    'digit_timeout' => 2500,
                    'numbers' => ['1' => ['module' => 'transfer']],
                ],
            ]]),
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
            ]]),
        ]);

        $client->update('account-1', 'device-1', new DeviceWriteData(
            name: 'Reception',
            deviceType: 'sip_device',
            enabled: true,
            sip: new DeviceSipData(
                customSipHeaders: new DeviceCustomSipHeadersData,
            ),
            musicOnHold: new DeviceMusicOnHoldData,
            outboundFlags: new DeviceOutboundFlagsData,
            dialPlan: new DeviceDialPlanData,
            metaflows: new DeviceMetaflowsData('#', null, 'self'),
        ));

        $body = (string) $this->history[1]['request']->getBody();
        $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR)['data'];

        self::assertStringContainsString('"music_on_hold":{}', $body);
        self::assertStringContainsString('"custom_sip_headers":{"in":{},"out":{}}', $body);
        self::assertSame([], $data['outbound_flags']);
        self::assertSame(['system' => []], $data['dial_plan']);
        self::assertSame('transfer', $data['metaflows']['numbers']['1']['module']);
        self::assertSame('#', $data['metaflows']['binding_digit']);
        self::assertArrayNotHasKey('digit_timeout', $data['metaflows']);
    }

    public function test_it_writes_supported_nested_device_configuration_without_flattening_it(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Mobile Operator',
                'device_type' => 'smartphone',
                'enabled' => true,
            ]]),
        ]);

        $client->create('account-1', new DeviceWriteData(
            name: 'Mobile Operator',
            deviceType: 'smartphone',
            enabled: true,
            callForward: new DeviceCallForwardData(
                enabled: true,
                number: '+15551234567',
                keepCallerId: true,
                requireKeypress: true,
            ),
            sip: new DeviceSipData(
                method: 'password',
                username: 'mobile-operator',
                password: 'a-long-random-secret',
                inviteFormat: 'contact',
                customSipHeaders: new DeviceCustomSipHeadersData(
                    inbound: ['X-Source' => 'carrier'],
                    outbound: ['X-Device' => 'reception'],
                ),
                customSipInterface: 'internal',
                forward: '192.0.2.10',
                proxy: '192.0.2.20',
                staticInvite: 'reception',
                transport: 'tcp',
            ),
            media: new DeviceMediaData(
                audioCodecs: ['OPUS', 'PCMU'],
                videoCodecs: ['H264'],
                enforceEncryption: true,
                encryptionMethods: ['srtp'],
            ),
            callerId: new DeviceCallerIdData(
                internalName: 'Mobile Operator',
                internalNumber: '1001',
                externalNumber: '+15557654321',
            ),
            callRecording: new DeviceCallRecordingData(
                inbound: new DeviceRecordingSourceData(
                    offnet: new DeviceRecordingParametersData(
                        enabled: true,
                        format: 'mp3',
                        minimumSeconds: 5,
                        recordOnAnswer: true,
                        timeLimit: 3600,
                    ),
                ),
            ),
            advanced: new DeviceAdvancedData(
                timezone: 'America/Los_Angeles',
                callWaiting: true,
                excludeFromContactList: false,
                outboundPrivacy: 'none',
            ),
            musicOnHold: new DeviceMusicOnHoldData('media-1'),
            outboundFlags: new DeviceOutboundFlagsData(['fax'], ['regional']),
            dialPlan: new DeviceDialPlanData(
                system: ['north_america'],
                rules: [[
                    'pattern' => '^([2-9][0-9]{6})$',
                    'description' => 'Local dialing',
                    'prefix' => '+1555',
                    'suffix' => null,
                ]],
            ),
            metaflows: new DeviceMetaflowsData('*', 2000, 'both'),
            flags: new DeviceFlagsData(['managed', 'audit']),
            formatters: new DeviceFormattersData([
                new DeviceFormatterRuleData(
                    field: 'request',
                    direction: 'outbound',
                    prefix: '+1',
                    regex: '^([2-9][0-9]{9})$',
                ),
            ]),
            provisioning: new DeviceProvisioningData(
                templateId: 'template-t54w',
                checkSyncEvent: 'check-sync',
                checkSyncReload: 'reboot=false',
                checkSyncReboot: 'reboot=true',
            ),
        ));

        self::assertSame([
            'data' => [
                'name' => 'Mobile Operator',
                'device_type' => 'smartphone',
                'enabled' => true,
                'provision' => [
                    'id' => 'template-t54w',
                    'check_sync_event' => 'check-sync',
                    'check_sync_reload' => 'reboot=false',
                    'check_sync_reboot' => 'reboot=true',
                ],
                'sip' => [
                    'method' => 'password',
                    'username' => 'mobile-operator',
                    'password' => 'a-long-random-secret',
                    'invite_format' => 'contact',
                    'custom_sip_interface' => 'internal',
                    'forward' => '192.0.2.10',
                    'proxy' => '192.0.2.20',
                    'static_invite' => 'reception',
                    'transport' => 'tcp',
                    'custom_sip_headers' => [
                        'in' => ['X-Source' => 'carrier'],
                        'out' => ['X-Device' => 'reception'],
                    ],
                ],
                'call_forward' => [
                    'enabled' => true,
                    'number' => '+15551234567',
                    'keep_caller_id' => true,
                    'require_keypress' => true,
                ],
                'media' => [
                    'audio' => ['codecs' => ['OPUS', 'PCMU']],
                    'video' => ['codecs' => ['H264']],
                    'encryption' => [
                        'enforce_security' => true,
                        'methods' => ['srtp'],
                    ],
                ],
                'caller_id' => [
                    'internal' => ['name' => 'Mobile Operator', 'number' => '1001'],
                    'external' => ['number' => '+15557654321'],
                ],
                'call_recording' => [
                    'inbound' => [
                        'offnet' => [
                            'enabled' => true,
                            'format' => 'mp3',
                            'record_min_sec' => 5,
                            'record_on_answer' => true,
                            'time_limit' => 3600,
                        ],
                    ],
                ],
                'music_on_hold' => ['media_id' => 'media-1'],
                'outbound_flags' => ['fax', 'regional'],
                'dial_plan' => [
                    'system' => ['north_america'],
                    '^([2-9][0-9]{6})$' => [
                        'description' => 'Local dialing',
                        'prefix' => '+1555',
                    ],
                ],
                'metaflows' => [
                    'binding_digit' => '*',
                    'digit_timeout' => 2000,
                    'listen_on' => 'both',
                ],
                'flags' => ['managed', 'audit'],
                'formatters' => [
                    'request' => [[
                        'direction' => 'outbound',
                        'prefix' => '+1',
                        'regex' => '^([2-9][0-9]{9})$',
                    ]],
                ],
                'timezone' => 'America/Los_Angeles',
                'call_waiting' => ['enabled' => true],
                'contact_list' => ['exclude' => false],
                'caller_id_options' => ['outbound_privacy' => 'none'],
            ],
        ], json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_it_deletes_a_device(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => []]),
        ]);

        $client->delete('account-1', 'device-1');

        self::assertSame('DELETE', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/devices/device-1', $this->history[0]['request']->getUri()->getPath());
    }

    public function test_it_requests_a_device_configuration_reload_or_reboot(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => 'sync request sent']),
            $this->response(['data' => 'sync request sent']),
        ]);

        $client->sync('account-1', 'device-1', false);
        $client->sync('account-1', 'device-1', true);

        self::assertSame('POST', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/devices/device-1/sync', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('reboot=false', $this->history[0]['request']->getUri()->getQuery());
        self::assertSame('reboot=true', $this->history[1]['request']->getUri()->getQuery());
    }

    public function test_it_rejects_an_update_response_for_a_different_device(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
            ]]),
            $this->response(['data' => [
                'id' => 'different-device',
                'name' => 'Wrong device',
                'device_type' => 'sip_device',
            ]]),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);
        $this->expectExceptionMessage('does not match');

        $client->update('account-1', 'device-1', new DeviceWriteData(
            name: 'Reception',
            deviceType: 'sip_device',
            enabled: true,
        ));
    }

    public function test_it_clears_catalog_selection_and_mac_without_erasing_unmanaged_provisioning_data(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'mac_address' => '00:11:22:33:44:55',
                'provision' => [
                    'endpoint_brand' => 'yealink',
                    'endpoint_family' => 't5',
                    'endpoint_model' => 't54w',
                    'unmanaged_provider_flag' => true,
                ],
            ]]),
            $this->response(['data' => [
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
            ]]),
        ]);

        $client->update('account-1', 'device-1', new DeviceWriteData(
            name: 'Reception',
            deviceType: 'sip_device',
            enabled: true,
            clearMissingProvisioningFields: true,
        ));

        $data = json_decode(
            (string) $this->history[1]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['data'];

        self::assertArrayNotHasKey('mac_address', $data);
        self::assertSame(['unmanaged_provider_flag' => true], $data['provision']);
    }

    public function test_it_returns_typed_registered_device_statuses(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                ['device_id' => 'device-1', 'registered' => true],
                ['device_id' => 'device-2', 'registered' => false],
            ]]),
        ]);

        $statuses = $client->statuses('account-1');

        self::assertCount(2, $statuses);
        self::assertSame('device-1', $statuses[0]->deviceId);
        self::assertTrue($statuses[0]->registered);
        self::assertSame('device-2', $statuses[1]->deviceId);
        self::assertFalse($statuses[1]->registered);
        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/devices/status', $this->history[0]['request']->getUri()->getPath());
    }

    public function test_it_rejects_a_status_without_a_device_identifier(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [['registered' => true]]]),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);
        $this->expectExceptionMessage('device_id');

        $client->statuses('account-1');
    }

    /**
     * @param  list<Response>  $responses
     */
    private function clientWithResponses(array $responses): DeviceResourceClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $http = new Client(['handler' => $stack]);
        $switch = new SwitchClient(
            $http,
            new SwitchConfig('http://switch.test/v2', 'unused-api-key'),
            $this->tokenProvider(),
        );

        return new DeviceResourceClient($switch);
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }

    private function tokenProvider(): TokenProvider
    {
        return new class implements TokenProvider
        {
            public function token(): string
            {
                return 'test-token';
            }

            public function invalidate(): void {}
        };
    }
}
