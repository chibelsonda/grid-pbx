<?php

namespace Tests\Unit\Domains\Devices;

use App\Domains\Devices\Gateways\CrossbarSwitchDeviceGateway;
use App\Domains\Devices\Services\DeviceMetaflowPolicy;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Devices\DeviceResourceClient;
use GridPbx\Switch\Domains\PhoneNumbers\PhoneNumberResourceClient;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class CrossbarSwitchDeviceGatewayTest extends TestCase
{
    public function test_update_maps_nullable_audited_strings_to_switch_clear_values_and_removes_owner(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            $this->response([
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'owner_id' => 'switch-user-1',
                'language' => 'en-US',
                'timezone' => 'America/Los_Angeles',
                'presence_id' => 'reception',
                'ringtones' => ['internal' => 'Inside', 'external' => 'Outside'],
                'metaflows' => ['binding_digit' => '*'],
                'flags' => ['legacy'],
                'formatters' => ['request' => [['prefix' => '+1']]],
                'sip' => [
                    'realm' => 'switch.example.test',
                    'custom_sip_interface' => 'internal',
                    'forward' => '192.0.2.10',
                    'proxy' => 'proxy.example.test',
                    'static_invite' => 'reception',
                    'transport' => 'tcp',
                ],
                'provision' => [
                    'id' => 'template-t54w',
                    'check_sync_event' => 'event',
                    'check_sync_reload' => 'reload',
                    'check_sync_reboot' => 'reboot',
                ],
            ]),
            $this->response([
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
            ]),
        ]));
        $stack->push(Middleware::history($history));
        $switch = new SwitchClient(
            new Client(['handler' => $stack]),
            new SwitchConfig('http://switch.test/v2', 'unused-api-key'),
            new class implements TokenProvider
            {
                public function token(): string
                {
                    return 'test-token';
                }

                public function invalidate(): void {}
            },
        );
        $gateway = new CrossbarSwitchDeviceGateway(
            new DeviceResourceClient($switch),
            new PhoneNumberResourceClient($switch),
            new DeviceMetaflowPolicy,
        );
        $account = (new SwitchAccount)->forceFill(['switch_account_id' => 'account-1']);

        $gateway->update($account, 'device-1', [
            'name' => 'Reception',
            'device_type' => 'sip_device',
            'is_enabled' => true,
            'owner_switch_resource_id' => null,
            'make' => null,
            'family' => null,
            'model' => null,
            'mac_address' => null,
            'sip_username' => null,
            'sip_password' => null,
            'sip' => [
                'realm' => null,
                'custom_sip_interface' => null,
                'forward' => null,
                'proxy' => null,
                'static_invite' => null,
                'transport' => null,
            ],
            'language' => null,
            'timezone' => null,
            'presence_id' => null,
            'ringtones' => ['internal' => null, 'external' => null],
            'flags' => [],
            'formatters' => [],
            'provision' => [
                'id' => null,
                'check_sync_event' => null,
                'check_sync_reload' => null,
                'check_sync_reboot' => null,
            ],
        ]);

        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame('POST', $history[1]['request']->getMethod());
        $payload = json_decode(
            (string) $history[1]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['data'];

        self::assertArrayNotHasKey('owner_id', $payload);
        self::assertArrayNotHasKey('language', $payload);
        self::assertArrayNotHasKey('timezone', $payload);
        self::assertArrayNotHasKey('presence_id', $payload);
        self::assertArrayNotHasKey('ringtones', $payload);
        self::assertSame(['binding_digit' => '*'], $payload['metaflows']);
        self::assertSame([], $payload['flags']);
        self::assertSame([], $payload['formatters']);
        self::assertArrayNotHasKey('sip', $payload);
        self::assertArrayNotHasKey('provision', $payload);
    }

    public function test_it_maps_the_connected_device_schema_to_ui_capabilities(): void
    {
        $switch = $this->switchWithResponses([
            $this->response([
                'id' => 'devices',
                'properties' => [
                    'call_forward' => ['properties' => [
                        'number' => ['type' => 'string', 'maxLength' => 35],
                    ]],
                    'sip' => ['properties' => [
                        'invite_format' => ['enum' => ['username', 'strip_plus']],
                        'proxy' => ['type' => 'string'],
                        'transport' => ['type' => 'string'],
                    ]],
                    'provision' => ['properties' => [
                        'id' => ['type' => 'string'],
                        'endpoint_model' => ['oneOf' => [['type' => 'string'], ['type' => 'array']]],
                    ]],
                ],
            ]),
        ]);
        $gateway = new CrossbarSwitchDeviceGateway(
            new DeviceResourceClient($switch),
            new PhoneNumberResourceClient($switch),
            new DeviceMetaflowPolicy,
        );

        $compatibility = $gateway->schemaCompatibility();

        self::assertSame('connected_switch', $compatibility['source']);
        self::assertSame(35, $compatibility['call_forward']['number_max_length']);
        self::assertSame(['username', 'strip_plus'], $compatibility['sip']['invite_formats']);
        self::assertTrue($compatibility['sip']['proxy']);
        self::assertFalse($compatibility['sip']['forward']);
        self::assertTrue($compatibility['provision']['template_id']);
        self::assertSame(['string', 'array'], $compatibility['provision']['endpoint_model_types']);
        self::assertFalse($compatibility['provision']['check_sync_event']);
    }

    public function test_guided_metaflow_update_preserves_locked_data_from_the_live_switch_snapshot(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            $this->response([
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'metaflows' => [
                    'numbers' => [
                        '1' => ['module' => 'transfer', 'data' => ['target' => '1001']],
                        '9' => ['module' => 'callflow', 'data' => ['id' => 'live-private-id']],
                    ],
                ],
            ]),
            $this->response([
                'id' => 'device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
            ]),
        ]));
        $stack->push(Middleware::history($history));
        $switch = new SwitchClient(
            new Client(['handler' => $stack]),
            new SwitchConfig('http://switch.test/v2', 'unused-api-key'),
            new class implements TokenProvider
            {
                public function token(): string
                {
                    return 'test-token';
                }

                public function invalidate(): void {}
            },
        );
        $gateway = new CrossbarSwitchDeviceGateway(
            new DeviceResourceClient($switch),
            new PhoneNumberResourceClient($switch),
            new DeviceMetaflowPolicy,
        );
        $account = (new SwitchAccount)->forceFill(['switch_account_id' => 'account-1']);

        $gateway->update($account, 'device-1', [
            'name' => 'Reception',
            'device_type' => 'sip_device',
            'is_enabled' => true,
            'owner_switch_resource_id' => null,
            'make' => null,
            'family' => null,
            'model' => null,
            'mac_address' => null,
            'sip_username' => null,
            'sip_password' => null,
            'metaflows' => [
                'actions' => [[
                    'trigger_type' => 'number',
                    'trigger' => '2',
                    'module' => 'hangup',
                    'data' => [],
                ]],
            ],
        ]);

        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame('POST', $history[1]['request']->getMethod());
        $payload = json_decode(
            (string) $history[1]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['data'];

        self::assertArrayNotHasKey('1', $payload['metaflows']['numbers']);
        self::assertSame('hangup', $payload['metaflows']['numbers']['2']['module']);
        self::assertSame('live-private-id', $payload['metaflows']['numbers']['9']['data']['id']);
        self::assertArrayNotHasKey('actions', $payload['metaflows']);
    }

    /** @param array<string, mixed> $data */
    private function response(array $data): Response
    {
        return new Response(200, [], json_encode([
            'status' => 'success',
            'data' => $data,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param list<Response> $responses */
    private function switchWithResponses(array $responses): SwitchClient
    {
        return new SwitchClient(
            new Client(['handler' => HandlerStack::create(new MockHandler($responses))]),
            new SwitchConfig('http://switch.test/v2', 'unused-api-key'),
            new class implements TokenProvider
            {
                public function token(): string
                {
                    return 'test-token';
                }

                public function invalidate(): void {}
            },
        );
    }
}
