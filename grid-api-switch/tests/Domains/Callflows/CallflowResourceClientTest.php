<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowCreateData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\ManagedExtensionCallflowWriteData;
use GridPbx\Switch\Domains\Callflows\CallflowResourceClient;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use JsonException;
use PHPUnit\Framework\TestCase;

final class CallflowResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_it_creates_a_guided_callflow(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-created',
            'name' => 'Main line',
            'numbers' => ['+15550000100'],
            'flow' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []],
        ]);

        $snapshot = $client->create('account-1', new CallflowCreateData(
            name: 'Main line',
            destinationModule: 'user',
            destinationResourceId: 'user-1',
            phoneNumbers: ['+15550000100'],
        ));

        self::assertSame('callflow-created', $snapshot->id);
        self::assertSame('PUT', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/callflows', $this->history[0]['request']->getUri()->getPath());
        self::assertStringContainsString('"children":{}', (string) $this->history[0]['request']->getBody());
        self::assertSame([
            'data' => [
                'name' => 'Main line',
                'numbers' => ['+15550000100'],
                'flow' => [
                    'module' => 'user',
                    'data' => ['id' => 'user-1'],
                    'children' => [],
                ],
            ],
        ], json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_it_creates_a_managed_extension_callflow_with_voicemail_fallback(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-created',
            'name' => 'Alice Operator',
            'numbers' => ['1001'],
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-1'],
                'children' => [
                    '_' => [
                        'module' => 'voicemail',
                        'data' => ['id' => 'voicemail-1'],
                        'children' => [],
                    ],
                ],
            ],
        ]);

        $snapshot = $client->create('account-1', new CallflowCreateData(
            name: 'Alice Operator',
            destinationModule: 'user',
            destinationResourceId: 'user-1',
            phoneNumbers: ['1001'],
            fallbackModule: 'voicemail',
            fallbackResourceId: 'voicemail-1',
        ));

        self::assertSame(['user', 'voicemail'], $snapshot->modules);
        self::assertSame([
            'data' => [
                'name' => 'Alice Operator',
                'numbers' => ['1001'],
                'flow' => [
                    'module' => 'user',
                    'data' => ['id' => 'user-1'],
                    'children' => [
                        '_' => [
                            'module' => 'voicemail',
                            'data' => ['id' => 'voicemail-1'],
                            'children' => [],
                        ],
                    ],
                ],
            ],
        ], json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    /** @throws JsonException */
    public function test_it_updates_only_the_root_destination_and_preserves_unknown_children(): void
    {
        $response = [
            'id' => 'callflow-1',
            'name' => 'Reception route',
            'numbers' => ['1000'],
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-2'],
                'children' => [
                    '_' => [
                        'module' => 'custom_module',
                        'data' => ['vendor_setting' => 'preserve-me'],
                        'children' => [],
                    ],
                ],
            ],
        ];
        $client = $this->clientWithResponse($response);

        $snapshot = $client->update('account-1', 'callflow-1', new CallflowWriteData(
            current: [
                'id' => 'callflow-1',
                '_rev' => '3-revision',
                'pvt_account_id' => 'account-1',
                'name' => 'Old route',
                'numbers' => ['1000', '+15550000001'],
                'flow' => [
                    'module' => 'play',
                    'data' => ['id' => 'old-media', 'endless_playback' => true],
                    'children' => $response['flow']['children'],
                ],
            ],
            destinationModule: 'user',
            destinationResourceId: 'user-2',
            name: 'Reception route',
            assignedPhoneNumbers: ['+15550000002'],
            knownPhoneNumbers: ['+15550000001', '+15550000002'],
        ));

        self::assertSame('callflow-1', $snapshot->id);
        self::assertSame('POST', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/callflows/callflow-1', $this->history[0]['request']->getUri()->getPath());
        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('user', $body['data']['flow']['module']);
        self::assertSame(['id' => 'user-2'], $body['data']['flow']['data']);
        self::assertSame(['1000', '+15550000002'], $body['data']['numbers']);
        self::assertSame('preserve-me', $body['data']['flow']['children']['_']['data']['vendor_setting']);
        self::assertArrayNotHasKey('id', $body['data']);
        self::assertArrayNotHasKey('_rev', $body['data']);
        self::assertArrayNotHasKey('pvt_account_id', $body['data']);
    }

    public function test_it_deletes_a_callflow(): void
    {
        $client = $this->clientWithResponse([]);

        $client->delete('account-1', 'callflow-1');

        self::assertSame('DELETE', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/callflows/callflow-1', $this->history[0]['request']->getUri()->getPath());
    }

    public function test_it_updates_a_managed_extension_number_and_voicemail_fallback_losslessly(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-1',
            'name' => 'Alice Support',
            'numbers' => ['1010', '+15550000100'],
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-1', 'timeout' => 25],
                'children' => [
                    '_' => [
                        'module' => 'voicemail',
                        'data' => ['id' => 'voicemail-2'],
                        'children' => [],
                    ],
                    'busy' => [
                        'module' => 'custom_vendor_module',
                        'data' => ['preserve' => true],
                        'children' => [],
                    ],
                ],
            ],
        ]);

        $client->updateManagedExtension(
            'account-1',
            'callflow-1',
            new ManagedExtensionCallflowWriteData(
                current: [
                    'id' => 'callflow-1',
                    '_rev' => '4-revision',
                    'name' => 'Alice Operator',
                    'numbers' => ['1001', '+15550000100'],
                    'flow' => [
                        'module' => 'user',
                        'data' => ['id' => 'user-1', 'timeout' => 25],
                        'children' => [
                            '_' => [
                                'module' => 'voicemail',
                                'data' => ['id' => 'voicemail-1'],
                                'children' => [],
                            ],
                            'busy' => [
                                'module' => 'custom_vendor_module',
                                'data' => ['preserve' => true],
                                'children' => [],
                            ],
                        ],
                    ],
                    'pvt_account_id' => 'account-1',
                ],
                userResourceId: 'user-1',
                previousExtension: '1001',
                extension: '1010',
                name: 'Alice Support',
                voicemailBoxResourceId: 'voicemail-2',
            ),
        );

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['1010', '+15550000100'], $body['data']['numbers']);
        self::assertSame(25, $body['data']['flow']['data']['timeout']);
        self::assertSame('voicemail-2', $body['data']['flow']['children']['_']['data']['id']);
        self::assertTrue($body['data']['flow']['children']['busy']['data']['preserve']);
        self::assertArrayNotHasKey('_rev', $body['data']);
        self::assertArrayNotHasKey('pvt_account_id', $body['data']);
    }

    /** @param array<string, mixed> $responseData */
    private function clientWithResponse(array $responseData): CallflowResourceClient
    {
        $this->history = [];
        $response = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => $responseData,
        ], JSON_THROW_ON_ERROR));
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->history));
        $switch = new SwitchClient(
            new Client(['handler' => $stack]),
            new SwitchConfig('http://switch.test/v2', 'unused-api-key'),
            new class implements TokenProvider
            {
                public function token(): string
                {
                    return 'test-token';
                }

                public function invalidate(): void
                {
                }
            },
        );

        return new CallflowResourceClient($switch);
    }
}
