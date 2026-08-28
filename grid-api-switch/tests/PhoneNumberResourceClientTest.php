<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Resources\PhoneNumberResourceClient;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class PhoneNumberResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_it_paginates_keyed_numbers_and_hydrates_typed_details(): void
    {
        $client = $this->clientWithResponses([
            $this->response([
                'data' => ['numbers' => [
                    '+14155550100' => ['state' => 'in_service'],
                    '+14155550101' => ['state' => 'reserved'],
                ]],
                'next_start_key' => '+14155550102',
            ]),
            $this->response(['data' => [
                'id' => '+14155550100',
                'state' => 'in_service',
                'used_by' => 'callflow',
                'features' => ['local', 'inbound_cnam'],
                'cnam' => ['display_name' => 'GridPBX', 'inbound_lookup' => true],
                '_read_only' => ['created' => 63627848989, 'modified' => 63627849999],
            ]]),
            $this->response(['data' => [
                'id' => '+14155550101',
                '_read_only' => ['state' => 'reserved', 'features' => ['local']],
            ]]),
            $this->response(['data' => ['numbers' => []]]),
        ], pageSize: 2);

        $numbers = iterator_to_array($client->allDetails('account-1'), false);

        self::assertCount(2, $numbers);
        self::assertSame('+14155550100', $numbers[0]->number);
        self::assertSame('in_service', $numbers[0]->state);
        self::assertSame('callflow', $numbers[0]->usedBy);
        self::assertSame(['local', 'inbound_cnam'], $numbers[0]->features);
        self::assertSame('GridPBX', $numbers[0]->cnamDisplayName);
        self::assertTrue($numbers[0]->cnamInboundLookup);
        self::assertSame('reserved', $numbers[1]->state);
        self::assertSame('/v2/accounts/account-1/phone_numbers/%2B14155550100', $this->history[1]['request']->getUri()->getPath());
        parse_str($this->history[3]['request']->getUri()->getQuery(), $query);
        self::assertSame('+14155550102', $query['start_key']);
    }

    public function test_it_rejects_a_detail_for_a_different_number(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => ['id' => '+14155550999']]),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);
        $this->expectExceptionMessage('does not match');

        $client->find('account-1', '+14155550100');
    }

    public function test_it_rejects_a_collection_without_numbers(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => []]),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);
        $this->expectExceptionMessage('data.numbers');

        iterator_to_array($client->allDetails('account-1'));
    }

    /** @param list<Response> $responses */
    private function clientWithResponses(array $responses, int $pageSize = 200): PhoneNumberResourceClient
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

        return new PhoneNumberResourceClient($switch, $pageSize);
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
