<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\CallerIdLists\CallerIdListResourceClient;
use GridPbx\Switch\Domains\CallerIdLists\Dto\CallerIdListEntryWriteData;
use GridPbx\Switch\Domains\CallerIdLists\Dto\CallerIdListWriteData;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class CallerIdListResourceClientTest extends TestCase
{
    public function test_it_loads_each_list_with_its_distinct_entries(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            $this->response(['data' => [['id' => 'list-1', 'name' => 'VIP callers']]]),
            $this->response(['data' => [
                'id' => 'list-1',
                'name' => 'VIP callers',
                'description' => 'Priority callers',
                'org' => 'GridPBX',
            ]]),
            $this->response(['data' => [[
                'id' => 'entry-1',
                'name' => 'Manila prefix',
            ]]]),
            $this->response(['data' => [
                'id' => 'entry-1',
                'list_id' => 'list-1',
                'displayname' => 'Manila prefix',
                'pattern' => '^\\+632',
            ]]),
        ]));
        $stack->push(Middleware::history($history));
        $switch = new SwitchClient(
            new Client(['handler' => $stack]),
            new SwitchConfig('http://switch.test/v2', 'unused'),
            new class implements TokenProvider
            {
                public function token(): string
                {
                    return 'test-token';
                }

                public function invalidate(): void {}
            },
        );

        $details = iterator_to_array(
            (new CallerIdListResourceClient($switch))->allDetails('account-1'),
            false,
        );

        self::assertCount(1, $details);
        self::assertSame('VIP callers', $details[0]->list->name);
        self::assertSame('Priority callers', $details[0]->list->description);
        self::assertSame('entry-1', $details[0]->entries[0]->id);
        self::assertSame('list-1', $details[0]->entries[0]->listId);
        self::assertSame('^\\+632', $details[0]->entries[0]->pattern);
        self::assertSame(
            [
                '/v2/accounts/account-1/lists',
                '/v2/accounts/account-1/lists/list-1',
                '/v2/accounts/account-1/lists/list-1/entries',
                '/v2/accounts/account-1/lists/list-1/entries/entry-1',
            ],
            array_map(fn (array $item): string => $item['request']->getUri()->getPath(), $history),
        );
        self::assertStringContainsString('paginate=true', $history[0]['request']->getUri()->getQuery());
        self::assertStringContainsString('page_size=200', $history[0]['request']->getUri()->getQuery());
        self::assertStringContainsString('page_size=200', $history[2]['request']->getUri()->getQuery());
    }

    public function test_it_writes_list_metadata_and_entries_to_the_distinct_endpoints(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            $this->response(['data' => ['id' => 'list-1', 'name' => 'VIP callers']]),
            $this->response(['data' => ['id' => 'list-1', 'name' => 'Priority callers']]),
            $this->response(['data' => ['id' => 'entry-1', 'list_id' => 'list-1', 'number' => '+1555']]),
            $this->response(['data' => ['id' => 'entry-1', 'list_id' => 'list-1', 'pattern' => '^\\+632']]),
            $this->response(['data' => []]),
            $this->response(['data' => []]),
        ]));
        $stack->push(Middleware::history($history));
        $client = new CallerIdListResourceClient(new SwitchClient(
            new Client(['handler' => $stack]),
            new SwitchConfig('http://switch.test/v2', 'unused'),
            new class implements TokenProvider
            {
                public function token(): string
                {
                    return 'test-token';
                }

                public function invalidate(): void {}
            },
        ));

        $client->create('account-1', new CallerIdListWriteData('VIP callers', 'Priority callers'));
        $client->update('account-1', 'list-1', new CallerIdListWriteData('Priority callers'));
        $client->createEntry('account-1', 'list-1', new CallerIdListEntryWriteData(null, '+1555', null));
        $client->updateEntry('account-1', 'list-1', 'entry-1', new CallerIdListEntryWriteData('Manila', null, '^\\+632'));
        $client->deleteEntry('account-1', 'list-1', 'entry-1');
        $client->delete('account-1', 'list-1');

        self::assertSame(['PUT', 'POST', 'PUT', 'POST', 'DELETE', 'DELETE'], array_map(
            fn (array $item): string => $item['request']->getMethod(),
            $history,
        ));
        self::assertSame('/v2/accounts/account-1/lists/list-1/entries/entry-1', $history[3]['request']->getUri()->getPath());
        $entryBody = json_decode((string) $history[3]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([
            'displayname' => 'Manila',
            'pattern' => '^\\+632',
            'list_id' => 'list-1',
        ], $entryBody['data']);
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
