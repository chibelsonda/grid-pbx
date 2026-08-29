<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Menus\Dto\MenuWriteData;
use GridPbx\Switch\Domains\Menus\MenuResourceClient;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class MenuResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_menu_client_maps_and_writes_supported_settings(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [['id' => 'menu-1', 'name' => 'Main menu']]]),
            $this->response(['data' => [
                'id' => 'menu-1', 'name' => 'Main menu', 'timeout' => 8000,
                'interdigit_timeout' => 1500, 'max_extension_length' => 5, 'retries' => 2,
                'hunt' => false, 'allow_record_from_offnet' => true, 'suppress_media' => true,
                'record_pin' => '1234', 'hunt_allow' => '^2', 'hunt_deny' => '^9',
                'media' => ['greeting' => 'media-1', 'invalid_media' => false, 'transfer_media' => true, 'exit_media' => 'media-2'],
            ]]),
            $this->response(['data' => [
                'id' => 'menu-2', 'name' => 'Support', 'media' => [], 'flags' => [],
            ]]),
        ]);
        $client = new MenuResourceClient($switch);

        $menus = iterator_to_array($client->allDetails('account-1'), false);
        $created = $client->create('account-1', new MenuWriteData(name: 'Support'));
        $body = json_decode((string) $this->history[2]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('menu-1', $menus[0]->id);
        self::assertSame('media-1', $menus[0]->greetingMediaId);
        self::assertFalse($menus[0]->invalidMedia);
        self::assertSame('menu-2', $created->id);
        self::assertArrayNotHasKey('greeting', $body['data']['media']);
        self::assertSame([], $body['data']['flags']);
        self::assertSame('/v2/accounts/account-1/menus', $this->history[2]['request']->getUri()->getPath());
    }

    public function test_update_preserves_a_write_only_record_pin_without_exposing_it_to_callers(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'id' => 'menu-1',
                'name' => 'Main menu',
                'record_pin' => '4826',
                'flags' => ['external-managed'],
                'media' => [],
            ]]),
            $this->response(['data' => [
                'id' => 'menu-1',
                'name' => 'Updated menu',
                'record_pin' => '4826',
                'flags' => ['external-managed'],
                'media' => [],
            ]]),
        ]);
        $client = new MenuResourceClient($switch);

        $updated = $client->update('account-1', 'menu-1', new MenuWriteData(
            name: 'Updated menu',
            flags: ['external-managed'],
        ));
        $body = json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('POST', $this->history[1]['request']->getMethod());
        self::assertSame('4826', $body['data']['record_pin']);
        self::assertSame(['external-managed'], $body['data']['flags']);
        self::assertSame(['external-managed'], $updated->flags);
    }

    /** @param list<Response> $responses */
    private function switchWithResponses(array $responses): SwitchClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new SwitchClient(new Client(['handler' => $stack]), new SwitchConfig('http://switch.test/v2', 'unused'), new class implements TokenProvider
        {
            public function token(): string
            {
                return 'test-token';
            }

            public function invalidate(): void {}
        });
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
