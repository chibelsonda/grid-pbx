<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Accounts\AccountResource;
use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\Domains\Accounts\Dto\AccountHierarchySnapshot;
use GridPbx\Switch\Domains\Users\Dto\UserSnapshot;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AccountResourceClientTest extends TestCase
{
    public function test_it_maps_typed_reseller_status_from_an_account_response(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'account-1',
                'name' => 'Primary reseller',
                'is_reseller' => true,
                'reseller_id' => 'master-account',
                'billing_mode' => 'limits_only',
                'superduper_admin' => true,
                'descendants_count' => 4,
            ]]),
        ]);

        $account = $client->account('account-1');

        self::assertTrue($account->isReseller);
        self::assertSame('master-account', $account->resellerId);
        self::assertSame('limits_only', $account->billingMode);
        self::assertTrue($account->superduperAdmin);
        self::assertSame(4, $account->descendantsCount);
    }

    public function test_it_fetches_typed_descendant_hierarchy_entries(): void
    {
        $responses = new MockHandler([
            $this->response(['data' => [
                [
                    'id' => 'child-1',
                    'name' => 'Child account',
                    'realm' => 'child.example.test',
                    'tree' => ['master-account', 'account-1'],
                    'descendants_count' => 2,
                ],
            ]]),
        ]);
        $history = [];
        $stack = HandlerStack::create($responses);
        $stack->push(Middleware::history($history));
        $client = new AccountResourceClient(new SwitchClient(
            new Client(['handler' => $stack]),
            new SwitchConfig('http://switch.test/v2', 'unused-api-key'),
            $this->tokenProvider(),
        ));

        $descendants = $client->descendants('account-1');

        self::assertCount(1, $descendants);
        self::assertInstanceOf(AccountHierarchySnapshot::class, $descendants[0]);
        self::assertSame('child-1', $descendants[0]->id);
        self::assertSame('account-1', $descendants[0]->parentId);
        self::assertSame(['master-account', 'account-1'], $descendants[0]->tree);
        self::assertSame(2, $descendants[0]->descendantsCount);
        self::assertSame('/v2/accounts/account-1/descendants', $history[0]['request']->getUri()->getPath());
        self::assertSame('GET', $history[0]['request']->getMethod());
    }

    public function test_it_fetches_every_collection_page_and_hydrates_full_detail_snapshots(): void
    {
        $responses = new MockHandler([
            $this->response(['data' => [['id' => 'user-1']], 'next_start_key' => 'page-2']),
            $this->response(['data' => [
                'id' => 'user-1',
                'username' => 'alice',
                'caller_id' => ['internal' => ['number' => '1001']],
                'custom_future_field' => ['enabled' => true],
            ]]),
            $this->response(['data' => [['id' => 'user-2']]]),
            $this->response(['data' => [
                'id' => 'user-2',
                'username' => 'bob',
                'caller_id' => ['internal' => ['number' => '1002']],
            ]]),
        ]);
        $history = [];
        $stack = HandlerStack::create($responses);
        $stack->push(Middleware::history($history));
        $http = new Client(['handler' => $stack]);
        $client = new SwitchClient(
            $http,
            new SwitchConfig('http://switch.test/v2', 'unused-api-key'),
            $this->tokenProvider(),
        );

        $snapshots = iterator_to_array(
            (new AccountResourceClient($client, 1))->allDetails('account-1', AccountResource::Users),
            false,
        );

        self::assertCount(2, $snapshots);
        self::assertInstanceOf(UserSnapshot::class, $snapshots[0]);
        self::assertSame('alice', $snapshots[0]->username);
        self::assertSame('1001', $snapshots[0]->internalCallerIdNumber);
        self::assertSame(['enabled' => true], $snapshots[0]->toArray()['custom_future_field']);
        self::assertSame('bob', $snapshots[1]->username);
        self::assertSame(
            [
                '/v2/accounts/account-1/users?paginate=true&page_size=1',
                '/v2/accounts/account-1/users/user-1?paginate=false',
                '/v2/accounts/account-1/users?paginate=true&page_size=1&start_key=page-2',
                '/v2/accounts/account-1/users/user-2?paginate=false',
            ],
            array_map(
                static fn (array $transaction): string => $transaction['request']->getUri()->getPath()
                    .($transaction['request']->getUri()->getQuery() !== '' ? '?'.$transaction['request']->getUri()->getQuery() : ''),
                $history,
            ),
        );
    }

    public function test_it_rejects_a_detail_document_for_a_different_resource(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => ['id' => 'different-user']]),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);
        $this->expectExceptionMessage('does not match');

        $client->find('account-1', AccountResource::Users, 'user-1');
    }

    public function test_it_rejects_a_repeated_pagination_cursor(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [], 'next_start_key' => 'same-cursor']),
            $this->response(['data' => [], 'next_start_key' => 'same-cursor']),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);
        $this->expectExceptionMessage('repeated cursor');

        iterator_to_array($client->allDetails('account-1', AccountResource::Users));
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }

    /** @param list<Response> $responses */
    private function clientWithResponses(array $responses): AccountResourceClient
    {
        $http = new Client([
            'handler' => HandlerStack::create(new MockHandler($responses)),
        ]);
        $client = new SwitchClient(
            $http,
            new SwitchConfig('http://switch.test/v2', 'unused-api-key'),
            $this->tokenProvider(),
        );

        return new AccountResourceClient($client, 1);
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
