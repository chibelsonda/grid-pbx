<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Directories\DirectoryResourceClient;
use GridPbx\Switch\Domains\Directories\Dto\DirectoryWriteData;
use GridPbx\Switch\Domains\Groups\Dto\GroupEndpointWriteData;
use GridPbx\Switch\Domains\Groups\Dto\GroupWriteData;
use GridPbx\Switch\Domains\Groups\GroupResourceClient;
use GridPbx\Switch\Domains\Users\Dto\UserDirectoryMappingsWriteData;
use GridPbx\Switch\Domains\Users\UserResourceClient;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class DirectoryAndGroupResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_directory_client_fetches_resolved_members_and_sends_settings_only(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [['id' => 'directory-1']]]),
            $this->response(['data' => [
                'id' => 'directory-1',
                'name' => 'People',
                'users' => [['user_id' => 'user-1', 'callflow_id' => 'callflow-1']],
            ]]),
            $this->response(['data' => [
                'id' => 'directory-2',
                'name' => 'Support',
                'confirm_match' => false,
                'min_dtmf' => 2,
                'max_dtmf' => 5,
                'sort_by' => 'first_name',
                'flags' => ['public-directory'],
                'users' => [],
            ]]),
        ]);
        $client = new DirectoryResourceClient($switch);

        $directories = iterator_to_array($client->allDetails('account-1'));
        $created = $client->create('account-1', new DirectoryWriteData(
            name: 'Support',
            confirmMatch: false,
            minDtmf: 2,
            maxDtmf: 5,
            sortBy: 'first_name',
            flags: ['public-directory'],
            preservedOptions: ['future_option' => ['nested' => 'keep']],
        ));
        $body = json_decode((string) $this->history[2]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('user-1', $directories[0]->members[0]->userId);
        self::assertSame('Support', $created->name);
        self::assertSame('PUT', $this->history[2]['request']->getMethod());
        self::assertArrayNotHasKey('users', $body['data']);
        self::assertSame('first_name', $body['data']['sort_by']);
        self::assertSame(['public-directory'], $body['data']['flags']);
        self::assertSame('keep', $body['data']['future_option']['nested']);
        self::assertSame(['public-directory'], $created->flags);
    }

    public function test_group_client_maps_typed_endpoints_and_music_on_hold(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'id' => 'group-1',
                'name' => 'Support team',
                'endpoints' => [
                    'user-1' => ['type' => 'user', 'weight' => 1],
                    'device-1' => ['type' => 'device', 'weight' => 2],
                ],
                'music_on_hold' => ['media_id' => 'media-1'],
                'flags' => ['external-managed'],
            ]]),
        ]);
        $client = new GroupResourceClient($switch);

        $group = $client->create('account-1', new GroupWriteData(
            name: 'Support team',
            endpoints: [
                new GroupEndpointWriteData('user-1', 'user', 1),
                new GroupEndpointWriteData('device-1', 'device', 2),
            ],
            musicOnHoldMediaId: 'media-1',
            flags: ['external-managed'],
            preservedOptions: [
                'future_option' => ['nested' => 'keep'],
                'music_on_hold' => ['media_id' => 'old-media', 'vendor_mode' => 'shuffle'],
                'endpoints' => [
                    'user-1' => ['type' => 'device', 'weight' => 99, 'vendor_alert' => true],
                    'removed-user' => ['vendor_alert' => true],
                ],
            ],
        ));
        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['user', 'device'], array_map(static fn ($endpoint): string => $endpoint->type, $group->endpoints));
        self::assertSame('media-1', $group->musicOnHoldMediaId);
        self::assertSame(['external-managed'], $group->flags);
        self::assertSame(2, $body['data']['endpoints']['device-1']['weight']);
        self::assertSame('user', $body['data']['endpoints']['user-1']['type']);
        self::assertTrue($body['data']['endpoints']['user-1']['vendor_alert']);
        self::assertArrayNotHasKey('removed-user', $body['data']['endpoints']);
        self::assertSame('shuffle', $body['data']['music_on_hold']['vendor_mode']);
        self::assertSame('media-1', $body['data']['music_on_hold']['media_id']);
        self::assertSame('keep', $body['data']['future_option']['nested']);
        self::assertSame(['external-managed'], $body['data']['flags']);
    }

    public function test_group_client_encodes_cleared_endpoints_and_music_on_hold_as_objects(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'id' => 'group-1',
                'name' => 'Support team',
                'endpoints' => [],
                'music_on_hold' => [],
            ]]),
        ]);
        $client = new GroupResourceClient($switch);

        $client->update('account-1', 'group-1', new GroupWriteData('Support team', []));
        $body = json_decode((string) $this->history[0]['request']->getBody(), flags: JSON_THROW_ON_ERROR);

        self::assertIsObject($body->data->endpoints);
        self::assertSame([], get_object_vars($body->data->endpoints));
        self::assertIsObject($body->data->music_on_hold);
        self::assertSame([], get_object_vars($body->data->music_on_hold));
    }

    public function test_group_write_data_enforces_the_installed_name_limit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GroupWriteData(str_repeat('x', 129), []);
    }

    public function test_user_client_patches_directory_mappings_without_replacing_user_profile(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'directories' => ['directory-old' => 'callflow-old'],
            ]]),
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'directories' => [
                    'directory-old' => 'callflow-old',
                    'directory-new' => 'callflow-new',
                ],
            ]]),
        ]);
        $client = new UserResourceClient($switch);

        $current = $client->get('account-1', 'user-1');
        $updated = $client->updateDirectoryMappings(
            'account-1',
            'user-1',
            new UserDirectoryMappingsWriteData([
                ...$current->directoryMappings,
                'directory-new' => 'callflow-new',
            ]),
        );
        $body = json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('callflow-new', $updated->directoryMappings['directory-new']);
        self::assertSame('PATCH', $this->history[1]['request']->getMethod());
        self::assertSame(['directories'], array_keys($body['data']));
    }

    public function test_user_client_encodes_cleared_directory_mappings_as_an_object(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'directories' => [],
            ]]),
        ]);
        $client = new UserResourceClient($switch);

        $client->updateDirectoryMappings(
            'account-1',
            'user-1',
            new UserDirectoryMappingsWriteData([]),
        );
        $body = json_decode((string) $this->history[0]['request']->getBody(), flags: JSON_THROW_ON_ERROR);

        self::assertIsObject($body->data->directories);
        self::assertSame([], get_object_vars($body->data->directories));
    }

    /** @param list<Response> $responses */
    private function switchWithResponses(array $responses): SwitchClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new SwitchClient(
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
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
