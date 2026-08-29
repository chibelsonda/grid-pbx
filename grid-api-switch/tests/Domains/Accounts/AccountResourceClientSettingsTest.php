<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\Domains\Accounts\Dto\MusicOnHoldWriteData;
use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AccountResourceClientSettingsTest extends TestCase
{
    public function test_it_reads_and_updates_account_music_on_hold(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            $this->response(['data' => [
                'id' => 'account-1',
                'name' => 'Support',
                'music_on_hold' => ['media_id' => 'media-old'],
            ]]),
            $this->response(['data' => [
                'id' => 'account-1',
                'name' => 'Support',
                'music_on_hold' => ['media_id' => 'media-new'],
            ]]),
            $this->response(['data' => [
                'id' => 'account-1',
                'name' => 'Support',
                'music_on_hold' => ['media_id' => ''],
            ]]),
        ]));
        $stack->push(Middleware::history($history));
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
        $client = new AccountResourceClient($switch);

        $current = $client->account('account-1');
        $updated = $client->updateMusicOnHold('account-1', new MusicOnHoldWriteData('media-new'));
        $cleared = $client->updateMusicOnHold('account-1', new MusicOnHoldWriteData(null));

        self::assertSame('media-old', $current->musicOnHoldMediaId);
        self::assertSame('media-new', $updated->musicOnHoldMediaId);
        self::assertNull($cleared->musicOnHoldMediaId);
        self::assertSame('PATCH', $history[1]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1', $history[1]['request']->getUri()->getPath());
        self::assertSame(
            ['data' => ['music_on_hold' => ['media_id' => 'media-new']]],
            json_decode((string) $history[1]['request']->getBody(), true),
        );
        self::assertSame(
            ['data' => ['music_on_hold' => ['media_id' => '']]],
            json_decode((string) $history[2]['request']->getBody(), true),
        );
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
