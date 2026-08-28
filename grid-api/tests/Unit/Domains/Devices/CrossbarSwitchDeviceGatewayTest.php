<?php

namespace Tests\Unit\Domains\Devices;

use App\Domains\Devices\Gateways\CrossbarSwitchDeviceGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Resources\DeviceResourceClient;
use GridPbx\Switch\Resources\PhoneNumberResourceClient;
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
            'language' => null,
            'timezone' => null,
            'presence_id' => null,
            'ringtones' => ['internal' => null, 'external' => null],
        ]);

        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame('POST', $history[1]['request']->getMethod());
        $payload = json_decode(
            (string) $history[1]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['data'];

        self::assertArrayNotHasKey('owner_id', $payload);
        self::assertSame('', $payload['language']);
        self::assertSame('', $payload['timezone']);
        self::assertSame('', $payload['presence_id']);
        self::assertSame(['internal' => '', 'external' => ''], $payload['ringtones']);
        self::assertSame(['binding_digit' => '*'], $payload['metaflows']);
    }

    /** @param array<string, mixed> $data */
    private function response(array $data): Response
    {
        return new Response(200, [], json_encode([
            'status' => 'success',
            'data' => $data,
        ], JSON_THROW_ON_ERROR));
    }
}
