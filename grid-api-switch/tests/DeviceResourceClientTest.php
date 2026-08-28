<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Dto\DeviceWriteData;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Resources\DeviceResourceClient;
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

    public function test_it_updates_and_unassigns_a_device_without_overwriting_sip_credentials(): void
    {
        $client = $this->clientWithResponses([
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

        self::assertSame('POST', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/devices/device-1', $this->history[0]['request']->getUri()->getPath());
        self::assertSame([
            'data' => [
                'name' => 'Shared Phone',
                'device_type' => 'sip_device',
                'enabled' => false,
                'owner_id' => null,
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

    public function test_it_rejects_an_update_response_for_a_different_device(): void
    {
        $client = $this->clientWithResponses([
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

    /**
     * @param list<Response> $responses
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

            public function invalidate(): void
            {
            }
        };
    }
}
