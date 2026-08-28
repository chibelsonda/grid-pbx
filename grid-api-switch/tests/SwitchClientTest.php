<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\ApiKeyTokenProvider;
use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class SwitchClientTest extends TestCase
{
    public function test_it_authenticates_and_sends_an_authenticated_request(): void
    {
        $responses = new MockHandler([
            new Response(201, [], json_encode([
                'status' => 'success',
                'auth_token' => 'test-token',
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'status' => 'success',
                'data' => ['id' => 'account-id'],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $http = new Client(['handler' => HandlerStack::create($responses)]);
        $config = new SwitchConfig('http://switch:8000/v2', 'test-api-key');
        $tokens = new ApiKeyTokenProvider($http, $config);

        $payload = (new SwitchClient($http, $config, $tokens))
            ->request('GET', 'accounts/account-id');

        self::assertSame('account-id', $payload['data']['id']);
    }

    public function test_it_replaces_an_expired_token_before_retrying_once(): void
    {
        $responses = new MockHandler([
            new Response(401, [], json_encode([
                'status' => 'error',
                'message' => 'expired token',
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'status' => 'success',
                'data' => ['id' => 'account-id'],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $history = [];
        $stack = HandlerStack::create($responses);
        $stack->push(Middleware::history($history));
        $http = new Client(['handler' => $stack]);
        $config = new SwitchConfig('http://switch:8000/v2', 'test-api-key');
        $tokens = new class implements TokenProvider
        {
            private int $version = 1;

            public function token(): string
            {
                return 'test-token-'.$this->version;
            }

            public function invalidate(): void
            {
                $this->version++;
            }
        };

        $payload = (new SwitchClient($http, $config, $tokens))
            ->request('GET', 'accounts/account-id');

        self::assertSame('account-id', $payload['data']['id']);
        self::assertSame('test-token-1', $history[0]['request']->getHeaderLine('X-Auth-Token'));
        self::assertSame('test-token-2', $history[1]['request']->getHeaderLine('X-Auth-Token'));
    }
}
