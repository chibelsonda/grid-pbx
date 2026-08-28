<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Resources\CallDetailRecordResourceClient;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class CallDetailRecordResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_it_paginates_normalized_call_records_for_a_bounded_unix_time_range(): void
    {
        $client = $this->clientWithResponses([
            $this->response([
                'data' => [[
                    'id' => '202608-cdr-1',
                    'call_id' => 'call-1',
                    'interaction_id' => 'interaction-1',
                    'direction' => 'inbound',
                    'caller_id_number' => '+14155550100',
                    'callee_id_number' => '1001',
                    'unix_timestamp' => '1787875200',
                    'duration_seconds' => '45',
                    'billing_seconds' => 30,
                    'hangup_cause' => 'NORMAL_CLEARING',
                ]],
                'next_start_key' => 'opaque-next-page',
            ]),
            $this->response(['data' => [[
                'id' => '202608-cdr-2',
                'call_id' => 'call-2',
                'direction' => 'outbound',
                'unix_timestamp' => '1787875260',
            ]]]),
        ], pageSize: 1);

        $records = iterator_to_array($client->all('account-1', 1787875000, 1787876000), false);

        self::assertCount(2, $records);
        self::assertSame('202608-cdr-1', $records[0]->id);
        self::assertSame('interaction-1', $records[0]->interactionId);
        self::assertSame(1787875200, $records[0]->startedAtUnix);
        self::assertSame(45, $records[0]->durationSeconds);
        self::assertSame(30, $records[0]->billingSeconds);
        self::assertSame('/v2/accounts/account-1/cdrs', $this->history[0]['request']->getUri()->getPath());
        parse_str($this->history[0]['request']->getUri()->getQuery(), $firstQuery);
        self::assertSame('63955094200', $firstQuery['created_from']);
        self::assertSame('63955095200', $firstQuery['created_to']);
        self::assertSame('1', $firstQuery['page_size']);
        parse_str($this->history[1]['request']->getUri()->getQuery(), $secondQuery);
        self::assertSame('opaque-next-page', $secondQuery['start_key']);
    }

    public function test_it_rejects_an_entry_without_a_safe_start_timestamp(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [['id' => 'cdr-1', 'call_id' => 'call-1']]]),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);
        $this->expectExceptionMessage('unix_timestamp');

        iterator_to_array($client->all('account-1', 1787875000, 1787876000));
    }

    public function test_it_rejects_a_repeated_pagination_cursor(): void
    {
        $record = ['id' => 'cdr-1', 'call_id' => 'call-1', 'unix_timestamp' => '1787875200'];
        $client = $this->clientWithResponses([
            $this->response(['data' => [$record], 'next_start_key' => 'same-page']),
            $this->response(['data' => [$record], 'next_start_key' => 'same-page']),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);
        $this->expectExceptionMessage('repeated cursor');

        iterator_to_array($client->all('account-1', 1787875000, 1787876000));
    }

    /** @param list<Response> $responses */
    private function clientWithResponses(array $responses, int $pageSize = 200): CallDetailRecordResourceClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
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

        return new CallDetailRecordResourceClient($switch, $pageSize);
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
