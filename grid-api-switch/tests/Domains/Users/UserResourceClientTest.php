<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\Domains\Users\Dto\Credentials\UserCredentialsData;
use GridPbx\Switch\Domains\Users\Dto\Hotdesk\UserHotdeskData;
use GridPbx\Switch\Domains\Users\Dto\UserAdvancedData;
use GridPbx\Switch\Domains\Users\Dto\UserWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Domains\Users\UserResourceClient;
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
                'username' => 'alice.operator',
                'require_password_update' => true,
                'caller_id' => ['internal' => ['number' => '1001']],
            ]]),
        ]);

        $snapshot = $client->create('account-1', new UserWriteData(
            firstName: 'Alice',
            lastName: 'Operator',
            extension: '1001',
            email: 'alice@example.test',
            timezone: 'America/Los_Angeles',
            advanced: new UserAdvancedData(
                language: 'en-US',
                presenceId: 'alice@pbx.example.test',
                callWaiting: false,
                doNotDisturb: true,
                excludeFromContactList: true,
                outboundPrivacy: 'name',
            ),
            hotdesk: new UserHotdeskData(
                enabled: true,
                id: '1001',
                keepLoggedInElsewhere: true,
                requirePin: true,
                pin: '2468',
            ),
            credentials: new UserCredentialsData(
                username: 'alice.operator',
                password: 'correct horse battery staple',
                requirePasswordUpdate: true,
            ),
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
                'presence_id' => 'alice@pbx.example.test',
                'email' => 'alice@example.test',
                'timezone' => 'America/Los_Angeles',
                'language' => 'en-US',
                'call_waiting' => ['enabled' => false],
                'do_not_disturb' => ['enabled' => true],
                'contact_list' => ['exclude' => true],
                'caller_id_options' => ['outbound_privacy' => 'name'],
                'require_password_update' => true,
                'username' => 'alice.operator',
                'password' => 'correct horse battery staple',
                'hotdesk' => [
                    'enabled' => true,
                    'keep_logged_in_elsewhere' => true,
                    'require_pin' => true,
                    'id' => '1001',
                    'pin' => '2468',
                ],
            ],
        ], json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertTrue($snapshot->requirePasswordUpdate);
    }

    public function test_it_updates_an_unchanged_login_without_resending_the_password(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Support',
                'username' => 'alice.operator',
                'require_password_update' => false,
            ]]),
        ]);

        $snapshot = $client->update('account-1', 'user-1', new UserWriteData(
            firstName: 'Alice',
            lastName: 'Support',
            extension: '1001',
            credentials: new UserCredentialsData(username: 'alice.operator'),
        ));
        $body = json_decode(
            (string) $this->history[0]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertCount(1, $this->history);
        self::assertSame('alice.operator', $body['data']['username']);
        self::assertArrayNotHasKey('password', $body['data']);
        self::assertFalse($snapshot->requirePasswordUpdate);
    }

    public function test_it_removes_login_credentials_without_sending_a_password(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Support',
                'require_password_update' => false,
            ]]),
        ]);

        $client->update('account-1', 'user-1', new UserWriteData(
            firstName: 'Alice',
            lastName: 'Support',
            extension: '1001',
            credentials: new UserCredentialsData(),
        ));
        $body = json_decode(
            (string) $this->history[0]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayNotHasKey('username', $body['data']);
        self::assertArrayNotHasKey('password', $body['data']);
        self::assertFalse($body['data']['require_password_update']);
    }

    public function test_it_preserves_a_write_only_hotdesk_pin_during_an_update(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Operator',
                'hotdesk' => ['enabled' => true, 'id' => '1001', 'require_pin' => true, 'pin' => '2468'],
            ]]),
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Support',
                'hotdesk' => ['enabled' => true, 'id' => '1001', 'require_pin' => true, 'pin' => '2468'],
            ]]),
        ]);

        $snapshot = $client->update('account-1', 'user-1', new UserWriteData(
            firstName: 'Alice',
            lastName: 'Support',
            extension: '1001',
            hotdesk: new UserHotdeskData(
                enabled: true,
                id: '1001',
                requirePin: true,
                preservePin: true,
            ),
        ));

        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('POST', $this->history[1]['request']->getMethod());
        self::assertSame(
            '2468',
            json_decode(
                (string) $this->history[1]['request']->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR,
            )['data']['hotdesk']['pin'],
        );
        self::assertTrue($snapshot->hotdeskPinConfigured);
    }

    public function test_it_clears_a_hotdesk_pin_without_reading_the_existing_secret(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Support',
                'hotdesk' => ['enabled' => true, 'id' => '1001', 'require_pin' => false],
            ]]),
        ]);

        $snapshot = $client->update('account-1', 'user-1', new UserWriteData(
            firstName: 'Alice',
            lastName: 'Support',
            extension: '1001',
            hotdesk: new UserHotdeskData(enabled: true, id: '1001'),
        ));

        $body = json_decode(
            (string) $this->history[0]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertCount(1, $this->history);
        self::assertArrayNotHasKey('pin', $body['data']['hotdesk']);
        self::assertFalse($snapshot->hotdeskPinConfigured);
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
