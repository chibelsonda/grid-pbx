<?php

declare(strict_types=1);

namespace GridPbx\Kazoo\Tests;

use GridPbx\Kazoo\ApiKeyTokenProvider;
use GridPbx\Kazoo\KazooClient;
use GridPbx\Kazoo\KazooConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class KazooClientTest extends TestCase
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
        $config = new KazooConfig('http://kazoo:8000/v2', 'test-api-key');
        $tokens = new ApiKeyTokenProvider($http, $config);

        $payload = (new KazooClient($http, $config, $tokens))
            ->request('GET', 'accounts/account-id');

        self::assertSame('account-id', $payload['data']['id']);
    }
}
