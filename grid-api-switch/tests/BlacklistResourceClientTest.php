<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Dto\Accounts\AccountBlacklistsWriteData;
use GridPbx\Switch\Dto\Blacklists\BlacklistWriteData;
use GridPbx\Switch\Resources\AccountResourceClient;
use GridPbx\Switch\Resources\BlacklistResourceClient;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class BlacklistResourceClientTest extends TestCase
{
    public function test_it_maps_numbers_and_updates_account_activation_separately(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            $this->response(['data' => ['id' => 'blacklist-1', 'name' => 'Spam', 'numbers' => ['+15550001000' => ['note' => 'Robocall']], 'should_block_anonymous' => true]]),
            $this->response(['data' => ['id' => 'account-1', 'name' => 'Support', 'blacklists' => ['blacklist-1']]]),
        ]));
        $stack->push(Middleware::history($history));
        $switch = new SwitchClient(new Client(['handler' => $stack]), new SwitchConfig('http://switch.test/v2', 'unused'), new class implements TokenProvider {
            public function token(): string { return 'test-token'; }
            public function invalidate(): void {}
        });

        $blacklist = (new BlacklistResourceClient($switch))->create('account-1', new BlacklistWriteData('Spam', ['+15550001000'], true));
        $account = (new AccountResourceClient($switch))->updateBlacklists('account-1', new AccountBlacklistsWriteData(['blacklist-1']));
        $createBody = json_decode((string) $history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $accountBody = json_decode((string) $history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['note' => 'Robocall'], $blacklist->numbers['+15550001000']);
        self::assertTrue($blacklist->shouldBlockAnonymous);
        self::assertSame([], $createBody['data']['numbers']['+15550001000']);
        self::assertSame(['blacklist-1'], $account->blacklistIds);
        self::assertSame(['data' => ['blacklists' => ['blacklist-1']]], $accountBody);
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
