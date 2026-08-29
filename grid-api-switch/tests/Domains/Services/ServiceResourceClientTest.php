<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Services\ServiceResourceClient;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ServiceResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_maps_safe_service_summary_fields(): void
    {
        $client = new ServiceResourceClient($this->switchWithResponses([$this->response(['data' => [
            'plans' => ['plan-1' => ['name' => 'Business']],
            'quantities' => ['account' => ['devices' => ['sip_device' => 3]], 'cascade' => [], 'manual' => []],
            'reseller' => ['id' => 'reseller-1', 'is_reseller' => false],
            'billing_cycle' => ['next' => 63955440000, 'period' => 1, 'unit' => 'month'],
            'status' => ['acceptable' => true, 'reason' => 'good standing'],
            'invoices' => [['summary' => ['today' => 2.5, 'recurring' => 9.99]]],
        ]])]));

        $summary = $client->summary('account-1');

        self::assertSame('plan-1', $summary->plans[0]->id);
        self::assertSame('sip_device', $summary->quantities[0]->item);
        self::assertSame(3.0, $summary->quantities[0]->quantity);
        self::assertTrue($summary->acceptable);
        self::assertSame(2.5, $summary->dueToday);
        self::assertSame(9.99, $summary->recurringAmount);
        self::assertSame('/v2/accounts/account-1/services/summary', $this->history[0]['request']->getUri()->getPath());
    }

    public function test_maps_limits_without_a_write_method(): void
    {
        $client = new ServiceResourceClient($this->switchWithResponses([$this->response(['data' => [
            'id' => 'limits', 'enabled' => true, 'allow_prepay' => true, 'allow_postpay' => false,
            'inbound_trunks' => 2, 'outbound_trunks' => 3, 'twoway_trunks' => 4, 'burst_trunks' => 1,
            'calls' => 20, 'resource_consuming_calls' => 10,
        ]])]));

        $limits = $client->limits('account-1');

        self::assertSame(2, $limits->inboundTrunks);
        self::assertSame(20, $limits->calls);
        self::assertSame('GET', $this->history[0]['request']->getMethod());
    }

    /** @param list<Response> $responses */
    private function switchWithResponses(array $responses): SwitchClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new SwitchClient(new Client(['handler' => $stack]), new SwitchConfig('http://switch.test/v2', 'unused'), new class implements TokenProvider
        {
            public function token(): string
            {
                return 'test-token';
            }

            public function invalidate(): void {}
        });
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
