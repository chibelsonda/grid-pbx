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

    public function test_it_exposes_only_capabilities_and_a_parked_call_count(): void
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
        ]);

        $status = (new OperationalStatusClient($switch))->inspect('account/one')->toArray();

        self::assertSame([
            'presence_subscription_diagnostics_available' => true,
            'parked_call_summary_available' => true,
            'active_parked_call_count' => 2,
        ], $status);
        self::assertSame('/v2/accounts/account%2Fone/presence', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('/v2/accounts/account%2Fone/parked_calls', $this->history[1]['request']->getUri()->getPath());
        self::assertStringNotContainsString('private', json_encode($status, JSON_THROW_ON_ERROR));
    }

    public function test_it_fails_each_probe_closed_without_hiding_other_results(): void
    {
        $switch = $this->switchWithResponses([
            new Response(503, [], json_encode(['status' => 'error'], JSON_THROW_ON_ERROR)),
            $this->response(['data' => ['slots' => []]]),
        ]);

        self::assertSame([
            'presence_subscription_diagnostics_available' => false,
            'parked_call_summary_available' => true,
            'active_parked_call_count' => 0,
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
