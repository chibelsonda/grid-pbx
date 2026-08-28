<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Dto\Users\UserWriteData;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Resources\UserResourceClient;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class UserResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_it_creates_a_user_with_a_bounded_extension_payload(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Operator',
                'caller_id' => ['internal' => ['number' => '1001']],
            ]]),
        ]);

        $snapshot = $client->create('account-1', new UserWriteData(
            firstName: 'Alice',
            lastName: 'Operator',
            extension: '1001',
            username: 'alice.operator',
            email: 'alice@example.test',
            timezone: 'America/Los_Angeles',
        ));

        self::assertSame('user-1', $snapshot->id);
        self::assertSame('PUT', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/users', $this->history[0]['request']->getUri()->getPath());
        self::assertSame([
            'data' => [
                'first_name' => 'Alice',
                'last_name' => 'Operator',
                'enabled' => true,
                'caller_id' => [
                    'internal' => [
                        'name' => 'Alice Operator',
                        'number' => '1001',
                    ],
                ],
                'presence_id' => '1001',
                'username' => 'alice.operator',
                'email' => 'alice@example.test',
                'timezone' => 'America/Los_Angeles',
            ],
        ], json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_it_updates_and_deletes_a_user(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Support',
                'caller_id' => ['internal' => ['number' => '1001']],
            ]]),
            $this->response(['data' => []]),
        ]);

        $client->update('account-1', 'user-1', new UserWriteData('Alice', 'Support', '1001'));
        $client->delete('account-1', 'user-1');

        self::assertSame('POST', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/users/user-1', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('DELETE', $this->history[1]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/users/user-1', $this->history[1]['request']->getUri()->getPath());
    }

    public function test_it_rejects_an_update_response_for_a_different_user(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'other-user',
                'first_name' => 'Wrong',
                'last_name' => 'User',
            ]]),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);
        $this->expectExceptionMessage('does not match');

        $client->update('account-1', 'user-1', new UserWriteData('Alice', 'Support', '1001'));
    }

    /** @param list<Response> $responses */
    private function clientWithResponses(array $responses): UserResourceClient
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

        return new UserResourceClient($switch);
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
