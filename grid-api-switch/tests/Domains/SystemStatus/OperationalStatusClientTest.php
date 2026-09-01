<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests\Domains\SystemStatus;

use GridPbx\Switch\Domains\SystemStatus\OperationalStatusClient;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class OperationalStatusClientTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $history = [];

    public function test_it_exposes_only_capabilities_and_aggregate_counts(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'timeout' => false,
                'private-extension' => [[
                    'contact' => 'sip:private@10.0.0.8',
                    'call_id' => 'private-call-id',
                ]],
            ]]),
            $this->response(['data' => ['slots' => [
                '101' => ['Call-ID' => 'private-call-id', 'Switch-URI' => 'private-switch-uri'],
                '102' => ['Presence-ID' => 'private@realm.test'],
            ]]]),
            $this->response(['data' => [
                ['id' => 'sms', 'modifiers' => ['private' => 'metadata']],
                ['id' => 'channel_create'],
            ]]),
            $this->response(['data' => [
                [
                    'id' => 'raw-hook-one',
                    'enabled' => true,
                    'uri' => 'https://private.example.test/hook',
                ],
                [
                    'id' => 'raw-hook-two',
                    'enabled' => false,
                    'custom_data' => ['private' => 'value'],
                ],
            ]]),
            $this->response(['data' => [[
                'id' => 'raw-sms-id',
                'body' => 'private SMS body',
                'from' => '+15550000001',
                'to' => '+15550000002',
            ]]]),
            $this->response(['data' => [[
                'id' => 'raw-mms-id',
                'body' => 'private MIME body',
                'from' => '+15550000003',
                'to' => '+15550000004',
            ]]]),
            $this->response(['data' => [[
                'id' => 'raw-port-request-id',
                'bill' => [
                    'account_number' => 'private-billing-account',
                    'pin' => 'private-port-pin',
                ],
                'numbers' => ['+15550000005' => []],
                'uploads' => ['bill.pdf' => ['content_type' => 'application/pdf']],
                'comments' => [['content' => 'private port comment']],
            ]]]),
            $this->response(['data' => [
                'maximal_prefix_length' => 10,
                'usable_carriers' => ['local', 'private-provider'],
                'usable_creation_states' => ['available', 'in_service', 'reserved'],
                'provider_credentials' => ['api_key' => 'private-carrier-key'],
            ]]),
            $this->response(['data' => ['raw-connectivity-id']]),
            $this->response(['data' => [[
                'id' => 'raw-resource-id',
                'name' => 'private-carrier-resource',
                'gateways' => [[
                    'server' => 'private-carrier.example.test',
                    'username' => 'private-user',
                    'password' => 'private-password',
                ]],
            ]]]),
        ]);

        $status = (new OperationalStatusClient($switch))->inspect('account/one')->toArray();

        self::assertSame([
            'presence_subscription_diagnostics_available' => true,
            'parked_call_summary_available' => true,
            'active_parked_call_count' => 2,
            'webhook_event_catalog_available' => true,
            'webhook_available_event_count' => 2,
            'webhook_configuration_summary_available' => true,
            'webhook_configured_count' => 2,
            'webhook_enabled_count' => 1,
            'sms_inventory_available' => true,
            'mms_inventory_available' => true,
            'port_request_inventory_available' => true,
            'number_carrier_configuration_available' => true,
            'connectivity_summary_available' => true,
            'connectivity_count' => 1,
            'local_resource_summary_available' => true,
            'local_resource_count' => 1,
        ], $status);
        self::assertSame('/v2/accounts/account%2Fone/presence', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('/v2/accounts/account%2Fone/parked_calls', $this->history[1]['request']->getUri()->getPath());
        self::assertSame('/v2/webhooks', $this->history[2]['request']->getUri()->getPath());
        self::assertSame('/v2/accounts/account%2Fone/webhooks', $this->history[3]['request']->getUri()->getPath());
        self::assertSame('/v2/accounts/account%2Fone/sms', $this->history[4]['request']->getUri()->getPath());
        self::assertSame('paginate=true&page_size=1', $this->history[4]['request']->getUri()->getQuery());
        self::assertSame('/v2/accounts/account%2Fone/mms', $this->history[5]['request']->getUri()->getPath());
        self::assertSame('paginate=true&page_size=1', $this->history[5]['request']->getUri()->getQuery());
        self::assertSame('/v2/accounts/account%2Fone/port_requests', $this->history[6]['request']->getUri()->getPath());
        self::assertSame('by_number=gridpbx-capability-probe', $this->history[6]['request']->getUri()->getQuery());
        self::assertSame('/v2/accounts/account%2Fone/phone_numbers/carriers_info', $this->history[7]['request']->getUri()->getPath());
        self::assertSame('/v2/accounts/account%2Fone/connectivity', $this->history[8]['request']->getUri()->getPath());
        self::assertSame('/v2/accounts/account%2Fone/resources', $this->history[9]['request']->getUri()->getPath());
        self::assertStringNotContainsString('private', json_encode($status, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('raw-hook', json_encode($status, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('raw-sms', json_encode($status, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('raw-mms', json_encode($status, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('raw-port', json_encode($status, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('private-provider', json_encode($status, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('private-carrier-key', json_encode($status, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('raw-connectivity', json_encode($status, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('raw-resource', json_encode($status, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('private-password', json_encode($status, JSON_THROW_ON_ERROR));
    }

    public function test_it_fails_each_probe_closed_without_hiding_other_results(): void
    {
        $switch = $this->switchWithResponses([
            new Response(503, [], json_encode(['status' => 'error'], JSON_THROW_ON_ERROR)),
            $this->response(['data' => ['slots' => []]]),
            new Response(503, [], json_encode(['status' => 'error'], JSON_THROW_ON_ERROR)),
            $this->response(['data' => []]),
            new Response(503, [], json_encode(['status' => 'error'], JSON_THROW_ON_ERROR)),
            $this->response(['data' => []]),
            new Response(503, [], json_encode(['status' => 'error'], JSON_THROW_ON_ERROR)),
            $this->response(['data' => [
                'maximal_prefix_length' => 10,
                'usable_carriers' => 'invalid',
                'usable_creation_states' => [],
            ]]),
            $this->response(['data' => [null]]),
            $this->response(['data' => []]),
        ]);

        self::assertSame([
            'presence_subscription_diagnostics_available' => false,
            'parked_call_summary_available' => true,
            'active_parked_call_count' => 0,
            'webhook_event_catalog_available' => false,
            'webhook_available_event_count' => null,
            'webhook_configuration_summary_available' => true,
            'webhook_configured_count' => 0,
            'webhook_enabled_count' => 0,
            'sms_inventory_available' => false,
            'mms_inventory_available' => true,
            'port_request_inventory_available' => false,
            'number_carrier_configuration_available' => false,
            'connectivity_summary_available' => false,
            'connectivity_count' => null,
            'local_resource_summary_available' => true,
            'local_resource_count' => 0,
        ], (new OperationalStatusClient($switch))->inspect('account-1')->toArray());
    }

    /** @param list<Response> $responses */
    private function switchWithResponses(array $responses): SwitchClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new SwitchClient(
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
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
