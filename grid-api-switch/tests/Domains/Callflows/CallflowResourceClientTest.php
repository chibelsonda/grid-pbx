<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Callflows\CallflowResourceClient;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowBranchWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowCreateData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowInlineNodeWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowTreeMoveData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowTreeNodeWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowTreeReorderData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\ManagedExtensionCallflowWriteData;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
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
        self::assertSame('GET', $this->history[1]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/callflows/callflow-created', $this->history[1]['request']->getUri()->getPath());
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

    public function test_it_projects_the_authoritative_callflow_returned_after_a_write(): void
    {
        $submitted = [
            'id' => 'callflow-1',
            'flow' => [
                'module' => 'device',
                'data' => ['id' => 'device-1'],
                'children' => [
                    '_' => [
                        'module' => 'unsupported_child',
                        'data' => [],
                        'children' => [],
                    ],
                ],
            ],
        ];
        $persisted = [
            'id' => 'callflow-1',
            'flow' => [
                'module' => 'device',
                'data' => ['id' => 'device-1'],
                'children' => [],
            ],
        ];
        $client = $this->clientWithResponse($submitted, $persisted);

        $snapshot = $client->update('account-1', 'callflow-1', new CallflowWriteData(
            current: $submitted,
            destinationModule: 'device',
            destinationResourceId: 'device-1',
        ));

        self::assertSame(1, $snapshot->nodeCount);
        self::assertSame([], $snapshot->flow?->children);
        self::assertSame('POST', $this->history[0]['request']->getMethod());
        self::assertSame('GET', $this->history[1]['request']->getMethod());
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

    /** @throws JsonException */
    public function test_it_preserves_module_specific_data_when_only_the_root_target_changes(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-1',
            'flow' => [
                'module' => 'voicemail',
                'data' => ['id' => 'mailbox-2', 'action' => 'compose'],
                'children' => [],
            ],
        ]);

        $client->update('account-1', 'callflow-1', new CallflowWriteData(
            current: [
                'id' => 'callflow-1',
                'flow' => [
                    'module' => 'voicemail',
                    'data' => ['id' => 'mailbox-1', 'action' => 'compose'],
                    'children' => [],
                ],
            ],
            destinationModule: 'voicemail',
            destinationResourceId: 'mailbox-2',
        ));

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(
            ['id' => 'mailbox-2', 'action' => 'compose'],
            $body['data']['flow']['data'],
        );
    }

    /** @throws JsonException */
    public function test_it_replaces_a_leaf_fallback_without_losing_same_module_settings(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-1',
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-1'],
                'children' => [
                    '_' => [
                        'module' => 'voicemail',
                        'data' => ['id' => 'mailbox-2', 'action' => 'compose'],
                        'children' => [],
                    ],
                ],
            ],
        ]);

        $client->update('account-1', 'callflow-1', new CallflowWriteData(
            current: [
                'id' => 'callflow-1',
                'flow' => [
                    'module' => 'user',
                    'data' => ['id' => 'user-1'],
                    'children' => [
                        '_' => [
                            'module' => 'voicemail',
                            'data' => ['id' => 'mailbox-1', 'action' => 'compose'],
                            'children' => [],
                        ],
                    ],
                ],
            ],
            destinationModule: 'user',
            destinationResourceId: 'user-1',
            replaceFallback: true,
            fallbackModule: 'voicemail',
            fallbackResourceId: 'mailbox-2',
        ));

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(
            ['id' => 'mailbox-2', 'action' => 'compose'],
            $body['data']['flow']['children']['_']['data'],
        );
    }

    /** @throws JsonException */
    public function test_it_explicitly_clears_only_the_wildcard_fallback(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-1',
            'flow' => ['module' => 'menu', 'data' => ['id' => 'menu-1'], 'children' => []],
        ]);

        $client->update('account-1', 'callflow-1', new CallflowWriteData(
            current: [
                'id' => 'callflow-1',
                'flow' => [
                    'module' => 'menu',
                    'data' => ['id' => 'menu-1'],
                    'children' => [
                        '1' => ['module' => 'user', 'data' => ['id' => 'sales'], 'children' => []],
                        '_' => ['module' => 'voicemail', 'data' => ['id' => 'mailbox-1'], 'children' => []],
                    ],
                ],
            ],
            destinationModule: 'menu',
            destinationResourceId: 'menu-1',
            replaceFallback: true,
        ));

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('_', $body['data']['flow']['children']);
        self::assertSame('sales', $body['data']['flow']['children']['1']['data']['id']);
    }

    /** @throws JsonException */
    public function test_it_creates_a_menu_with_typed_key_branches(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-menu',
            'flow' => ['module' => 'menu', 'data' => ['id' => 'menu-1'], 'children' => []],
        ]);

        $client->create('account-1', new CallflowCreateData(
            name: 'Main IVR',
            destinationModule: 'menu',
            destinationResourceId: 'menu-1',
            phoneNumbers: ['+15550000100'],
            branchRoutes: [
                new CallflowBranchWriteData('0', 'user', 'sales-user'),
                new CallflowBranchWriteData('timeout', 'voicemail', 'operator-mailbox'),
            ],
        ));

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('sales-user', $body['data']['flow']['children']['0']['data']['id']);
        self::assertSame('operator-mailbox', $body['data']['flow']['children']['timeout']['data']['id']);
        self::assertStringContainsString(
            '"children":{"0":',
            (string) $this->history[0]['request']->getBody(),
        );
    }

    /** @throws JsonException */
    public function test_it_updates_only_explicit_menu_keys_and_preserves_legacy_and_unknown_branches(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-menu',
            'flow' => ['module' => 'menu', 'data' => ['id' => 'menu-1'], 'children' => []],
        ]);

        $client->update('account-1', 'callflow-menu', new CallflowWriteData(
            current: [
                'id' => 'callflow-menu',
                'flow' => [
                    'module' => 'menu',
                    'data' => ['id' => 'menu-1'],
                    'children' => [
                        '1' => ['module' => 'user', 'data' => ['id' => 'old-user', 'timeout' => 20], 'children' => []],
                        '2' => ['module' => 'voicemail', 'data' => ['id' => 'old-mailbox'], 'children' => []],
                        '#' => ['module' => 'custom_legacy', 'data' => ['preserve' => true], 'children' => []],
                        'vendor' => ['module' => 'custom_vendor', 'data' => ['preserve' => true], 'children' => []],
                    ],
                ],
            ],
            destinationModule: 'menu',
            destinationResourceId: 'menu-1',
            branchOperations: [
                new CallflowBranchWriteData('1', 'user', 'new-user'),
                new CallflowBranchWriteData('2', null, null),
                new CallflowBranchWriteData('*', 'voicemail', 'operator-mailbox'),
            ],
        ));

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $children = $body['data']['flow']['children'];

        self::assertSame(['id' => 'new-user', 'timeout' => 20], $children['1']['data']);
        self::assertArrayNotHasKey('2', $children);
        self::assertSame('operator-mailbox', $children['*']['data']['id']);
        self::assertTrue($children['#']['data']['preserve']);
        self::assertTrue($children['vendor']['data']['preserve']);
    }

    /** @throws JsonException */
    public function test_it_creates_a_rule_set_route_with_match_and_fallback_branches(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-hours',
            'flow' => ['module' => 'temporal_route', 'data' => ['rule_set' => 'set-1'], 'children' => []],
        ]);

        $client->create('account-1', new CallflowCreateData(
            name: 'Office hours',
            destinationModule: 'temporal_route',
            destinationResourceId: 'set-1',
            phoneNumbers: ['+15550000100'],
            fallbackModule: 'voicemail',
            fallbackResourceId: 'closed-mailbox',
            branchRoutes: [
                new CallflowBranchWriteData('rule_set', 'user', 'reception-user'),
            ],
        ));

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('set-1', $body['data']['flow']['data']['rule_set']);
        self::assertSame('reception-user', $body['data']['flow']['children']['rule_set']['data']['id']);
        self::assertSame('closed-mailbox', $body['data']['flow']['children']['_']['data']['id']);
    }

    /** @throws JsonException */
    public function test_it_creates_and_reorders_a_direct_temporal_rule_route(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-direct-hours',
            'flow' => ['module' => 'temporal_route', 'data' => ['rules' => ['rule-1', 'rule-2']], 'children' => []],
        ]);

        $client->create('account-1', new CallflowCreateData(
            name: 'Direct office hours',
            destinationModule: 'temporal_route',
            destinationResourceId: null,
            phoneNumbers: ['+15550000101'],
            destinationTemporalRuleIds: ['rule-1', 'rule-2'],
            branchRoutes: [
                new CallflowBranchWriteData('rule-1', 'user', 'weekday-user'),
                new CallflowBranchWriteData('rule-2', 'voicemail', 'holiday-mailbox'),
            ],
        ));

        $created = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['rules' => ['rule-1', 'rule-2']], $created['data']['flow']['data']);
        self::assertSame('weekday-user', $created['data']['flow']['children']['rule-1']['data']['id']);
        self::assertSame('holiday-mailbox', $created['data']['flow']['children']['rule-2']['data']['id']);

        $client = $this->clientWithResponse([
            'id' => 'callflow-direct-hours',
            'flow' => ['module' => 'temporal_route', 'data' => ['rules' => ['rule-2', 'rule-1']], 'children' => []],
        ]);
        $client->update('account-1', 'callflow-direct-hours', new CallflowWriteData(
            current: [
                'id' => 'callflow-direct-hours',
                'flow' => [
                    'module' => 'temporal_route',
                    'data' => [
                        'rule_set' => 'old-set',
                        'timezone' => 'America/New_York',
                    ],
                    'children' => [
                        '_' => ['module' => 'voicemail', 'data' => ['id' => 'closed'], 'children' => []],
                        'rule-1' => ['module' => 'user', 'data' => ['id' => 'weekday-user'], 'children' => []],
                        'rule-2' => ['module' => 'voicemail', 'data' => ['id' => 'holiday-mailbox'], 'children' => []],
                        'vendor' => ['module' => 'custom', 'data' => ['preserve' => true], 'children' => []],
                    ],
                ],
            ],
            destinationModule: 'temporal_route',
            destinationResourceId: null,
            branchOperations: [
                new CallflowBranchWriteData('rule-1', null, null),
                new CallflowBranchWriteData('rule-2', 'user', 'holiday-user'),
            ],
            destinationTemporalRuleIds: ['rule-2'],
        ));

        $updated = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(
            ['timezone' => 'America/New_York', 'rules' => ['rule-2']],
            $updated['data']['flow']['data'],
        );
        self::assertSame('closed', $updated['data']['flow']['children']['_']['data']['id']);
        self::assertArrayNotHasKey('rule-1', $updated['data']['flow']['children']);
        self::assertSame('holiday-user', $updated['data']['flow']['children']['rule-2']['data']['id']);
        self::assertTrue($updated['data']['flow']['children']['vendor']['data']['preserve']);
    }

    /** @throws JsonException */
    public function test_it_replaces_and_clears_only_the_rule_set_match_branch(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-hours',
            'flow' => ['module' => 'temporal_route', 'data' => ['rule_set' => 'set-1'], 'children' => []],
        ]);

        $client->update('account-1', 'callflow-hours', new CallflowWriteData(
            current: [
                'id' => 'callflow-hours',
                'flow' => [
                    'module' => 'temporal_route',
                    'data' => ['rule_set' => 'set-1', 'timezone' => 'America/New_York'],
                    'children' => [
                        'rule_set' => ['module' => 'user', 'data' => ['id' => 'old-user', 'timeout' => 20], 'children' => []],
                        '_' => ['module' => 'voicemail', 'data' => ['id' => 'closed-mailbox'], 'children' => []],
                        'legacy-rule' => ['module' => 'custom', 'data' => ['preserve' => true], 'children' => []],
                    ],
                ],
            ],
            destinationModule: 'temporal_route',
            destinationResourceId: 'set-2',
            branchOperations: [
                new CallflowBranchWriteData('rule_set', 'user', 'new-user'),
            ],
        ));

        $updated = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['rule_set' => 'set-2', 'timezone' => 'America/New_York'], $updated['data']['flow']['data']);
        self::assertSame(['id' => 'new-user', 'timeout' => 20], $updated['data']['flow']['children']['rule_set']['data']);
        self::assertTrue($updated['data']['flow']['children']['legacy-rule']['data']['preserve']);

        $client = $this->clientWithResponse([
            'id' => 'callflow-hours',
            'flow' => ['module' => 'temporal_route', 'data' => ['rule_set' => 'set-2'], 'children' => []],
        ]);
        $client->update('account-1', 'callflow-hours', new CallflowWriteData(
            current: $updated['data'],
            destinationModule: 'temporal_route',
            destinationResourceId: 'set-2',
            branchOperations: [
                new CallflowBranchWriteData('rule_set', null, null),
            ],
        ));

        $cleared = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('rule_set', $cleared['data']['flow']['children']);
        self::assertTrue($cleared['data']['flow']['children']['legacy-rule']['data']['preserve']);
    }

    public function test_it_deletes_a_callflow(): void
    {
        $client = $this->clientWithResponse([]);

        $client->delete('account-1', 'callflow-1');

        self::assertSame('DELETE', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/callflows/callflow-1', $this->history[0]['request']->getUri()->getPath());
    }

    /** @throws JsonException */
    public function test_it_moves_a_public_subtree_without_rebuilding_its_node_data(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-1',
            'flow' => ['module' => 'menu', 'data' => ['id' => 'menu-1'], 'children' => []],
        ]);

        $client->moveTreeNode('account-1', 'callflow-1', new CallflowTreeMoveData(
            current: [
                'id' => 'callflow-1',
                '_rev' => '4-revision',
                'pvt_account_id' => 'account-1',
                'flow' => [
                    'module' => 'menu',
                    'data' => ['id' => 'menu-1'],
                    'children' => [
                        '1' => [
                            'module' => 'user',
                            'data' => ['id' => 'user-1', 'timeout' => 20],
                            'children' => [
                                '_' => [
                                    'module' => 'voicemail',
                                    'data' => ['id' => 'mailbox-1', 'action' => 'compose'],
                                    'children' => [],
                                ],
                            ],
                        ],
                        '2' => [
                            'module' => 'group',
                            'data' => ['id' => 'group-1'],
                            'children' => [],
                        ],
                    ],
                ],
            ],
            sourcePath: ['1'],
            destinationParentPath: ['2'],
            destinationBranch: '_',
        ));

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $flow = $body['data']['flow'];

        self::assertSame('POST', $this->history[0]['request']->getMethod());
        self::assertArrayNotHasKey('1', $flow['children']);
        self::assertSame('user-1', $flow['children']['2']['children']['_']['data']['id']);
        self::assertSame(20, $flow['children']['2']['children']['_']['data']['timeout']);
        self::assertSame(
            'mailbox-1',
            $flow['children']['2']['children']['_']['children']['_']['data']['id'],
        );
        self::assertArrayNotHasKey('_rev', $body['data']);
        self::assertArrayNotHasKey('pvt_account_id', $body['data']);
    }

    public function test_it_rejects_preserved_paths_cycles_and_occupied_destinations(): void
    {
        $current = [
            'flow' => [
                'module' => 'menu',
                'data' => ['id' => 'menu-1'],
                'children' => [
                    '1' => [
                        'module' => 'user',
                        'data' => ['id' => 'user-1'],
                        'children' => [
                            '_' => ['module' => 'voicemail', 'data' => ['id' => 'box-1'], 'children' => []],
                        ],
                    ],
                    '2' => ['module' => 'group', 'data' => ['id' => 'group-1'], 'children' => []],
                ],
            ],
        ];

        foreach ([
            [['preserved_1'], [], '_'],
            [['1'], ['1', '_'], '_'],
            [['2'], ['1'], '_'],
        ] as [$source, $destination, $branch]) {
            try {
                new CallflowTreeMoveData($current, $source, $destination, $branch);
                self::fail('Unsafe callflow tree move was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    /** @throws JsonException */
    public function test_it_adds_a_guided_reference_node_to_an_empty_public_branch(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-1',
            'flow' => ['module' => 'menu', 'data' => ['id' => 'menu-1'], 'children' => []],
        ]);

        $client->writeTreeNode(
            'account-1',
            'callflow-1',
            CallflowTreeNodeWriteData::create(
                current: [
                    'id' => 'callflow-1',
                    '_rev' => '4-revision',
                    'flow' => [
                        'module' => 'menu',
                        'data' => ['id' => 'menu-1'],
                        'children' => [
                            '1' => [
                                'module' => 'user',
                                'data' => ['id' => 'user-1'],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
                parentPath: ['1'],
                branch: '_',
                module: 'voicemail',
                resourceId: 'mailbox-1',
            ),
        );

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $node = $body['data']['flow']['children']['1']['children']['_'];

        self::assertSame('POST', $this->history[0]['request']->getMethod());
        self::assertSame('voicemail', $node['module']);
        self::assertSame(['id' => 'mailbox-1'], $node['data']);
        self::assertSame([], $node['children']);
        self::assertArrayNotHasKey('_rev', $body['data']);
    }

    /** @throws JsonException */
    public function test_it_retargets_a_guided_node_and_preserves_its_data_and_children(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-1',
            'flow' => ['module' => 'menu', 'data' => ['id' => 'menu-1'], 'children' => []],
        ]);

        $client->writeTreeNode(
            'account-1',
            'callflow-1',
            CallflowTreeNodeWriteData::update(
                current: [
                    'flow' => [
                        'module' => 'menu',
                        'data' => ['id' => 'menu-1'],
                        'children' => [
                            '1' => [
                                'module' => 'user',
                                'data' => ['id' => 'user-1', 'timeout' => 25],
                                'children' => [
                                    '_' => [
                                        'module' => 'voicemail',
                                        'data' => ['id' => 'mailbox-1'],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                nodePath: ['1'],
                module: 'user',
                resourceId: 'user-2',
            ),
        );

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $node = $body['data']['flow']['children']['1'];

        self::assertSame('user-2', $node['data']['id']);
        self::assertSame(25, $node['data']['timeout']);
        self::assertSame('mailbox-1', $node['children']['_']['data']['id']);
    }

    /** @throws JsonException */
    public function test_it_inserts_a_descendant_before_its_ancestor_losslessly(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-1',
            'flow' => ['module' => 'menu', 'data' => ['id' => 'menu-1'], 'children' => []],
        ]);
        $current = [
            'flow' => [
                'module' => 'menu',
                'data' => ['id' => 'menu-1'],
                'children' => [
                    '1' => [
                        'module' => 'user',
                        'data' => ['id' => 'user-1', 'timeout' => 20],
                        'children' => [
                            '_' => [
                                'module' => 'play',
                                'data' => ['id' => 'media-1', 'terminators' => ['#']],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $client->reorderTreeNodes(
            'account-1',
            'callflow-1',
            new CallflowTreeReorderData($current, 'insert_before', ['1', '_'], ['1']),
        );

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $first = $body['data']['flow']['children']['1'];

        self::assertSame('play', $first['module']);
        self::assertSame(['#'], $first['data']['terminators']);
        self::assertSame('user', $first['children']['_']['module']);
        self::assertSame(20, $first['children']['_']['data']['timeout']);
        self::assertSame([], $first['children']['_']['children']);
    }

    /** @throws JsonException */
    public function test_it_swaps_disjoint_subtrees_without_rebuilding_them(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-1',
            'flow' => ['module' => 'menu', 'data' => ['id' => 'menu-1'], 'children' => []],
        ]);
        $current = [
            'flow' => [
                'module' => 'menu',
                'data' => ['id' => 'menu-1'],
                'children' => [
                    '1' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []],
                    '2' => ['module' => 'group', 'data' => ['id' => 'group-1'], 'children' => []],
                ],
            ],
        ];

        $client->reorderTreeNodes(
            'account-1',
            'callflow-1',
            new CallflowTreeReorderData($current, 'swap', ['1'], ['2']),
        );

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('group-1', $body['data']['flow']['children']['1']['data']['id']);
        self::assertSame('user-1', $body['data']['flow']['children']['2']['data']['id']);
    }

    public function test_it_rejects_an_inline_action_on_a_branch_the_parent_module_does_not_support(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('destination branch is not valid');

        CallflowInlineNodeWriteData::create(
            [
                'flow' => [
                    'module' => 'user',
                    'data' => ['id' => 'user-1'],
                    'children' => [],
                ],
            ],
            [],
            'timeout',
            'sleep',
            ['duration' => 1, 'unit' => 's', 'skip_module' => false],
        );
    }

    public function test_it_rejects_children_under_terminal_switch_actions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('destination branch is not valid');

        CallflowInlineNodeWriteData::create(
            [
                'flow' => [
                    'module' => 'response',
                    'data' => ['code' => 486],
                    'children' => [],
                ],
            ],
            [],
            '_',
            'sleep',
            ['duration' => 1, 'unit' => 's', 'skip_module' => false],
        );
    }

    public function test_it_creates_and_updates_call_priority_branch_variables_without_rebuilding_children(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-1'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'branch_variable',
            [
                'variable' => 'call_priority',
                'scope' => 'custom_channel_vars',
                'skip_module' => false,
            ],
        )->toSwitchData();
        $createdChildren = (array) $created['flow']['children'];
        $createdNode = $createdChildren['_'];

        self::assertSame('branch_variable', $createdNode['module']);
        self::assertSame('call_priority', $createdNode['data']['variable']);
        self::assertSame('custom_channel_vars', $createdNode['data']['scope']);
        self::assertFalse($createdNode['data']['skip_module']);

        $current = [
            'flow' => [
                ...$created['flow'],
                'children' => [
                    '_' => [
                        ...$createdNode,
                        'data' => [...$createdNode['data'], 'server_owned' => 'preserve'],
                        'children' => [
                            '42' => [
                                'module' => 'user',
                                'data' => ['id' => 'user-42'],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'branch_variable',
            [
                'variable' => 'call_priority',
                'scope' => 'custom_channel_vars',
                'skip_module' => true,
            ],
        )->toSwitchData();
        $updatedChildren = (array) $updated['flow']['children'];
        $updatedNode = $updatedChildren['_'];
        $updatedNodeChildren = (array) $updatedNode['children'];

        self::assertTrue($updatedNode['data']['skip_module']);
        self::assertSame('preserve', $updatedNode['data']['server_owned']);
        self::assertSame('user-42', $updatedNodeChildren['42']['data']['id']);
    }

    public function test_it_rejects_unsupported_call_priority_branch_variable_settings(): void
    {
        $base = ['flow' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []]];

        foreach ([
            ['variable' => 'private_variable', 'scope' => 'custom_channel_vars', 'skip_module' => false],
            ['variable' => 'call_priority', 'scope' => 'account', 'skip_module' => false],
        ] as $settings) {
            try {
                CallflowInlineNodeWriteData::create($base, [], '_', 'branch_variable', $settings);
                self::fail('Unsupported branch variable settings must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_it_writes_branch_bnumber_modes_and_exact_capture_branches_safely(): void
    {
        $base = [
            'flow' => [
                'module' => 'branch_bnumber',
                'data' => ['hunt' => false, 'server_owned' => 'preserve'],
                'children' => [],
            ],
        ];
        $branched = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '1000',
            'hangup',
            ['skip_module' => false],
        )->toSwitchData();
        $branchChildren = (array) $branched['flow']['children'];

        self::assertSame('hangup', $branchChildren['1000']['module']);

        $hunted = CallflowInlineNodeWriteData::create(
            ['flow' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []]],
            [],
            '_',
            'branch_bnumber',
            [
                'hunt' => true,
                'hunt_allow' => '^1\\d{3}$',
                'hunt_deny' => '^1900$',
                'skip_module' => false,
            ],
        )->toSwitchData();
        $huntNode = ((array) $hunted['flow']['children'])['_'];

        self::assertTrue($huntNode['data']['hunt']);
        self::assertSame('^1\\d{3}$', $huntNode['data']['hunt_allow']);
        self::assertSame('^1900$', $huntNode['data']['hunt_deny']);

        foreach ([
            ['hunt' => false, 'hunt_allow' => '^1', 'hunt_deny' => null, 'skip_module' => false],
            ['hunt' => true, 'hunt_allow' => '(?R)', 'hunt_deny' => null, 'skip_module' => false],
        ] as $settings) {
            try {
                CallflowInlineNodeWriteData::create($base, [], '_', 'branch_bnumber', $settings);
                self::fail('Invalid Branch BNumber settings must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        try {
            CallflowInlineNodeWriteData::update(
                [
                    'flow' => [
                        'module' => 'user',
                        'data' => ['id' => 'user-1'],
                        'children' => [
                            '_' => [
                                'module' => 'branch_bnumber',
                                'data' => ['hunt' => false],
                                'children' => [
                                    '1000' => [
                                        'module' => 'hangup',
                                        'data' => [],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                ['_'],
                'branch_bnumber',
                ['hunt' => true, 'hunt_allow' => null, 'hunt_deny' => null, 'skip_module' => false],
            );
            self::fail('Hunt mode must not make exact capture branches unreachable.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    public function test_it_writes_set_cav_variables_and_preserves_unmanaged_node_data(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-1'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'set_variables',
            [
                'custom_application_vars' => ['account_code' => 'support', 'priority-1' => '42'],
                'export' => true,
                'skip_module' => false,
            ],
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame(
            ['account_code' => 'support', 'priority-1' => '42'],
            (array) $createdNode['data']['custom_application_vars'],
        );
        self::assertTrue($createdNode['data']['export']);

        $createdNode['data']['server_owned'] = 'preserve';
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'set_variables',
            [
                'custom_application_vars' => ['queue' => 'sales'],
                'export' => false,
                'skip_module' => true,
            ],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertSame(['queue' => 'sales'], (array) $updatedNode['data']['custom_application_vars']);
        self::assertFalse($updatedNode['data']['export']);
        self::assertTrue($updatedNode['data']['skip_module']);
        self::assertSame('preserve', $updatedNode['data']['server_owned']);

        foreach ([
            ['bad key' => 'value'],
            ['valid' => "line\nbreak"],
        ] as $variables) {
            try {
                CallflowInlineNodeWriteData::create($base, [], '_', 'set_variables', [
                    'custom_application_vars' => $variables,
                    'export' => false,
                    'skip_module' => false,
                ]);
                self::fail('Invalid custom application variables must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_it_writes_manual_presence_and_preserves_unmanaged_node_data(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-1'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'manual_presence',
            ['presence_id' => '1001', 'status' => 'busy', 'skip_module' => false],
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame('1001', $createdNode['data']['presence_id']);
        self::assertSame('busy', $createdNode['data']['status']);

        $createdNode['data']['server_owned'] = 'preserve';
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'manual_presence',
            ['presence_id' => '1001@example.com', 'status' => 'idle', 'skip_module' => true],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertSame('1001@example.com', $updatedNode['data']['presence_id']);
        self::assertSame('idle', $updatedNode['data']['status']);
        self::assertTrue($updatedNode['data']['skip_module']);
        self::assertSame('preserve', $updatedNode['data']['server_owned']);

        foreach (['', 'bad id', 'one@two@example.com'] as $presenceId) {
            try {
                CallflowInlineNodeWriteData::create($base, [], '_', 'manual_presence', [
                    'presence_id' => $presenceId,
                    'status' => 'ringing',
                    'skip_module' => false,
                ]);
                self::fail('Invalid Manual Presence identifiers must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_it_writes_one_group_pickup_target_and_preserves_private_restrictions(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-root'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'group_pickup',
            ['device_id' => 'device-1', 'skip_module' => false],
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame('device-1', $createdNode['data']['device_id']);
        self::assertArrayNotHasKey('user_id', $createdNode['data']);
        self::assertArrayNotHasKey('group_id', $createdNode['data']);

        $createdNode['data']['approved_group_id'] = 'private-approval-group';
        $createdNode['data']['server_owned'] = 'preserve';
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'group_pickup',
            ['user_id' => 'user-2', 'skip_module' => true],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertSame('user-2', $updatedNode['data']['user_id']);
        self::assertArrayNotHasKey('device_id', $updatedNode['data']);
        self::assertArrayNotHasKey('group_id', $updatedNode['data']);
        self::assertTrue($updatedNode['data']['skip_module']);
        self::assertSame('private-approval-group', $updatedNode['data']['approved_group_id']);
        self::assertSame('preserve', $updatedNode['data']['server_owned']);

        foreach ([
            ['skip_module' => false],
            ['device_id' => 'device-1', 'group_id' => 'group-1', 'skip_module' => false],
            ['group_id' => '', 'skip_module' => false],
        ] as $invalidSettings) {
            try {
                CallflowInlineNodeWriteData::create($base, [], '_', 'group_pickup', $invalidSettings);
                self::fail('Group Pickup must reject missing, ambiguous, and empty targets.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_it_writes_receive_fax_and_preserves_unknown_media_settings(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-root'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'receive_fax',
            ['owner_id' => 'user-1', 'media' => ['fax_option' => 'auto'], 'skip_module' => false],
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame('user-1', $createdNode['data']['owner_id']);
        self::assertSame('auto', $createdNode['data']['media']['fax_option']);

        $createdNode['data']['media']['server_owned'] = 'preserve';
        $createdNode['data']['server_owned'] = 'preserve-node';
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'receive_fax',
            ['owner_id' => 'user-2', 'media' => ['fax_option' => true], 'skip_module' => true],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertSame('user-2', $updatedNode['data']['owner_id']);
        self::assertTrue($updatedNode['data']['media']['fax_option']);
        self::assertSame('preserve', $updatedNode['data']['media']['server_owned']);
        self::assertSame('preserve-node', $updatedNode['data']['server_owned']);
        self::assertTrue($updatedNode['data']['skip_module']);

        foreach ([null, 'invalid'] as $faxOption) {
            try {
                CallflowInlineNodeWriteData::create($base, [], '_', 'receive_fax', [
                    'owner_id' => 'user-1',
                    'media' => ['fax_option' => $faxOption],
                    'skip_module' => false,
                ]);
                self::fail('Receive Fax must reject unsupported fax options.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_it_writes_ring_group_toggle_and_preserves_unknown_settings(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-root'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'ring_group_toggle',
            ['action' => 'login', 'callflow_id' => 'ring-group-callflow', 'skip_module' => false],
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame('login', $createdNode['data']['action']);
        self::assertSame('ring-group-callflow', $createdNode['data']['callflow_id']);
        self::assertFalse($createdNode['data']['skip_module']);

        $createdNode['data']['server_owned'] = ['preserve' => true];
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'ring_group_toggle',
            ['action' => 'logout', 'callflow_id' => 'other-ring-group', 'skip_module' => true],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertSame('logout', $updatedNode['data']['action']);
        self::assertSame('other-ring-group', $updatedNode['data']['callflow_id']);
        self::assertTrue($updatedNode['data']['skip_module']);
        self::assertSame(['preserve' => true], $updatedNode['data']['server_owned']);

        foreach (['toggle', ''] as $action) {
            try {
                CallflowInlineNodeWriteData::create($base, [], '_', 'ring_group_toggle', [
                    'action' => $action,
                    'callflow_id' => 'ring-group-callflow',
                    'skip_module' => false,
                ]);
                self::fail('Ring Group Toggle must reject unsupported actions.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_it_writes_acdc_queue_actions_and_preserves_unknown_settings(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-root'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'acdc_queue',
            ['action' => 'login', 'id' => 'queue-1', 'skip_module' => false],
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame('login', $createdNode['data']['action']);
        self::assertSame('queue-1', $createdNode['data']['id']);
        self::assertFalse($createdNode['data']['skip_module']);

        $createdNode['data']['server_owned'] = ['preserve' => true];
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'acdc_queue',
            ['action' => 'logout', 'id' => 'queue-2', 'skip_module' => true],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertSame('logout', $updatedNode['data']['action']);
        self::assertSame('queue-2', $updatedNode['data']['id']);
        self::assertTrue($updatedNode['data']['skip_module']);
        self::assertSame(['preserve' => true], $updatedNode['data']['server_owned']);

        foreach (['toggle', ''] as $action) {
            try {
                CallflowInlineNodeWriteData::create($base, [], '_', 'acdc_queue', [
                    'action' => $action,
                    'id' => 'queue-1',
                    'skip_module' => false,
                ]);
                self::fail('ACDC Queue must reject unsupported actions.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_it_writes_hotdesk_actions_and_preserves_unknown_settings(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-root'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'hotdesk',
            ['action' => 'login', 'skip_module' => false],
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame('login', $createdNode['data']['action']);
        self::assertFalse($createdNode['data']['skip_module']);

        $createdNode['data']['id'] = 'server-selected-user';
        $createdNode['data']['interdigit_timeout'] = 2750;
        $createdNode['data']['server_owned'] = ['preserve' => true];
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'hotdesk',
            ['action' => 'toggle', 'skip_module' => true],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertSame('toggle', $updatedNode['data']['action']);
        self::assertTrue($updatedNode['data']['skip_module']);
        self::assertSame('server-selected-user', $updatedNode['data']['id']);
        self::assertSame(2750, $updatedNode['data']['interdigit_timeout']);
        self::assertSame(['preserve' => true], $updatedNode['data']['server_owned']);

        try {
            CallflowInlineNodeWriteData::create($base, [], '_', 'hotdesk', [
                'action' => 'bridge',
                'skip_module' => false,
            ]);
            self::fail('Hotdesking must reject the bridge action from the public editor.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    public function test_it_writes_resource_free_dnd_actions_and_preserves_server_owned_settings(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-root'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'do_not_disturb',
            ['action' => 'activate', 'skip_module' => false],
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame('activate', $createdNode['data']['action']);
        self::assertFalse($createdNode['data']['skip_module']);

        $createdNode['data']['id'] = 'server-selected-device';
        $createdNode['data']['server_owned'] = ['preserve' => true];
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'do_not_disturb',
            ['action' => 'toggle', 'skip_module' => true],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertSame('toggle', $updatedNode['data']['action']);
        self::assertTrue($updatedNode['data']['skip_module']);
        self::assertSame('server-selected-device', $updatedNode['data']['id']);
        self::assertSame(['preserve' => true], $updatedNode['data']['server_owned']);

        foreach ([
            ['action' => 'enable', 'skip_module' => false],
            ['action' => 'activate', 'id' => 'raw-user-id', 'skip_module' => false],
        ] as $settings) {
            try {
                CallflowInlineNodeWriteData::create($base, [], '_', 'do_not_disturb', $settings);
                self::fail('Do Not Disturb must reject legacy actions and public raw IDs.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_it_writes_bounded_device_page_groups_and_preserves_endpoint_fields(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-root'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'page_group',
            [
                'audio' => 'one-way',
                'endpoints' => [
                    ['endpoint_type' => 'device', 'id' => 'device-1'],
                    ['endpoint_type' => 'device', 'id' => 'device-2'],
                ],
                'skip_module' => false,
            ],
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame('one-way', $createdNode['data']['audio']);
        self::assertCount(2, $createdNode['data']['endpoints']);

        $createdNode['data']['timeout'] = 5;
        $createdNode['data']['endpoints'][0]['delay'] = 0;
        $createdNode['data']['endpoints'][0]['timeout'] = 20;
        $createdNode['data']['endpoints'][0]['server_owned'] = 'preserve-endpoint';
        $createdNode['data']['server_owned'] = 'preserve-node';
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'page_group',
            [
                'audio' => 'two-way',
                'endpoints' => [
                    ['endpoint_type' => 'device', 'id' => 'device-1'],
                    ['endpoint_type' => 'device', 'id' => 'device-3'],
                ],
                'skip_module' => true,
            ],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertSame('two-way', $updatedNode['data']['audio']);
        self::assertSame(['device-1', 'device-3'], array_column($updatedNode['data']['endpoints'], 'id'));
        self::assertSame('preserve-endpoint', $updatedNode['data']['endpoints'][0]['server_owned']);
        self::assertSame(0, $updatedNode['data']['endpoints'][0]['delay']);
        self::assertSame(20, $updatedNode['data']['endpoints'][0]['timeout']);
        self::assertArrayNotHasKey('server_owned', $updatedNode['data']['endpoints'][1]);
        self::assertSame(5, $updatedNode['data']['timeout']);
        self::assertSame('preserve-node', $updatedNode['data']['server_owned']);
        self::assertTrue($updatedNode['data']['skip_module']);

        $unsafe = $current;
        $unsafe['flow']['children']['_']['data']['barge_calls'] = true;

        $this->expectException(InvalidArgumentException::class);
        CallflowInlineNodeWriteData::update(
            $unsafe,
            ['_'],
            'page_group',
            [
                'audio' => 'one-way',
                'endpoints' => [['endpoint_type' => 'device', 'id' => 'device-1']],
                'skip_module' => false,
            ],
        );
    }

    public function test_it_writes_bounded_device_ring_groups_and_preserves_private_fields(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-root'],
                'children' => [],
            ],
        ];
        $settings = [
            'strategy' => 'simultaneous',
            'endpoints' => [[
                'endpoint_type' => 'device',
                'id' => 'device-1',
                'delay' => 5,
                'timeout' => 20,
            ]],
            'repeats' => 2,
            'timeout' => 25,
            'ignore_forward' => true,
            'fail_on_single_reject' => false,
            'skip_module' => false,
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'ring_group',
            $settings,
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame($settings, $createdNode['data']);

        $createdNode['data']['ringback'] = 'private-media-id';
        $createdNode['data']['endpoints'][0]['weight'] = 25;
        $createdNode['data']['endpoints'][0]['server_owned'] = 'preserve-endpoint';
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'ring_group',
            [
                'strategy' => 'single',
                'endpoints' => [[
                    'endpoint_type' => 'device',
                    'id' => 'device-1',
                    'delay' => 0,
                    'timeout' => 30,
                ]],
                'repeats' => 1,
                'timeout' => 30,
                'ignore_forward' => false,
                'fail_on_single_reject' => true,
                'skip_module' => true,
            ],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertSame('single', $updatedNode['data']['strategy']);
        self::assertSame(30, $updatedNode['data']['timeout']);
        self::assertSame('private-media-id', $updatedNode['data']['ringback']);
        self::assertFalse($updatedNode['data']['ignore_forward']);
        self::assertTrue($updatedNode['data']['fail_on_single_reject']);
        self::assertSame(25, $updatedNode['data']['endpoints'][0]['weight']);
        self::assertSame('preserve-endpoint', $updatedNode['data']['endpoints'][0]['server_owned']);
        self::assertSame(0, $updatedNode['data']['endpoints'][0]['delay']);
        self::assertSame(30, $updatedNode['data']['endpoints'][0]['timeout']);
        self::assertTrue($updatedNode['data']['skip_module']);

        $weightedCurrent = ['flow' => $base['flow']];
        $weightedCurrent['flow']['children'] = ['_' => $updatedNode];
        $weighted = CallflowInlineNodeWriteData::update(
            $weightedCurrent,
            ['_'],
            'ring_group',
            [
                'strategy' => 'weighted_random',
                'endpoints' => [[
                    'endpoint_type' => 'device',
                    'id' => 'device-1',
                    'delay' => 0,
                    'timeout' => 30,
                    'weight' => 75,
                ]],
                'repeats' => 1,
                'timeout' => 30,
                'ignore_forward' => false,
                'fail_on_single_reject' => true,
                'skip_module' => true,
            ],
        )->toSwitchData();
        $weightedNode = ((array) $weighted['flow']['children'])['_'];

        self::assertSame('weighted_random', $weightedNode['data']['strategy']);
        self::assertSame(75, $weightedNode['data']['endpoints'][0]['weight']);
        self::assertSame('preserve-endpoint', $weightedNode['data']['endpoints'][0]['server_owned']);

        $malformedCurrent = $weightedCurrent;
        $malformedCurrent['flow']['children']['_']['data']['ignore_forward'] = 'true';

        try {
            CallflowInlineNodeWriteData::update($malformedCurrent, ['_'], 'ring_group', $settings);
            self::fail('Ring Group must reject malformed existing bridge flags.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        CallflowInlineNodeWriteData::update($weightedCurrent, ['_'], 'ring_group', [
            ...$settings,
            'strategy' => 'weighted_random',
            'endpoints' => [[
                'endpoint_type' => 'device',
                'id' => 'device-1',
                'delay' => 0,
                'timeout' => 20,
            ]],
            'timeout' => 20,
        ]);
    }

    public function test_it_writes_conference_service_without_a_resource_id_and_preserves_unknown_settings(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-root'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'conference',
            ['skip_module' => false],
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame('conference', $createdNode['module']);
        self::assertSame(['skip_module' => false], $createdNode['data']);
        self::assertArrayNotHasKey('id', $createdNode['data']);

        $createdNode['data']['conference_service_prompt'] = 'server-owned';
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'conference',
            ['skip_module' => true],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertTrue($updatedNode['data']['skip_module']);
        self::assertSame('server-owned', $updatedNode['data']['conference_service_prompt']);

        $configured = $current;
        $configured['flow']['children']['_']['data']['id'] = 'conference-raw-id';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configured conference destinations');
        CallflowInlineNodeWriteData::update(
            $configured,
            ['_'],
            'conference',
            ['skip_module' => false],
        );
    }

    public function test_it_writes_check_voicemail_without_a_mailbox_id_and_preserves_unknown_settings(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-root'],
                'children' => [],
            ],
        ];
        $created = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'voicemail',
            ['action' => 'check', 'skip_module' => false],
        )->toSwitchData();
        $createdNode = ((array) $created['flow']['children'])['_'];

        self::assertSame('voicemail', $createdNode['module']);
        self::assertSame(['action' => 'check', 'skip_module' => false], $createdNode['data']);
        self::assertArrayNotHasKey('id', $createdNode['data']);

        $createdNode['data']['callerid_match_login'] = true;
        $createdNode['data']['private_prompt_id'] = 'server-owned';
        $current = ['flow' => $base['flow']];
        $current['flow']['children'] = ['_' => $createdNode];
        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['_'],
            'voicemail',
            ['action' => 'check', 'skip_module' => true],
        )->toSwitchData();
        $updatedNode = ((array) $updated['flow']['children'])['_'];

        self::assertTrue($updatedNode['data']['skip_module']);
        self::assertTrue($updatedNode['data']['callerid_match_login']);
        self::assertSame('server-owned', $updatedNode['data']['private_prompt_id']);

        $configured = $current;
        $configured['flow']['children']['_']['data'] = [
            'action' => 'compose',
            'id' => 'voicemail-box-raw-id',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configured voicemail destinations');
        CallflowInlineNodeWriteData::update(
            $configured,
            ['_'],
            'voicemail',
            ['action' => 'check', 'skip_module' => false],
        );
    }

    public function test_it_preserves_exact_recording_action_variants_in_inline_nodes(): void
    {
        $base = [
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'user-1'],
                'children' => [],
            ],
        ];

        $recording = CallflowInlineNodeWriteData::create(
            $base,
            [],
            '_',
            'record_call',
            [
                'action' => 'stop',
                'format' => null,
                'label' => null,
                'record_min_sec' => null,
                'record_on_answer' => false,
                'record_on_bridge' => false,
                'record_sample_rate' => null,
                'should_follow_transfer' => true,
                'time_limit' => 3600,
                'skip_module' => false,
            ],
        )->toSwitchData();
        $recordingChildren = (array) $recording['flow']['children'];

        self::assertSame('stop', $recordingChildren['_']['data']['action']);
    }

    public function test_it_rejects_call_forward_writes_and_preserves_existing_private_data(): void
    {
        $forwarding = [
            'module' => 'call_forward',
            'data' => [
                'action' => 'activate',
                'number' => '+15551234567',
                'future_option' => ['preserve' => true],
            ],
            'children' => [
                '_' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []],
            ],
        ];
        $current = [
            'flow' => [
                'module' => 'menu',
                'data' => ['id' => 'menu-1'],
                'children' => [
                    '1' => $forwarding,
                    '2' => ['module' => 'hangup', 'data' => ['skip_module' => false], 'children' => []],
                ],
            ],
        ];

        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['2'],
            'hangup',
            ['skip_module' => true],
        )->toSwitchData();
        $children = (array) $updated['flow']['children'];

        self::assertSame($forwarding['data'], $children['1']['data']);
        self::assertSame(
            'user',
            ((array) $children['1']['children'])['_']['module'],
        );
        self::assertTrue($children['2']['data']['skip_module']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inline Switch callflow action is not supported');
        CallflowInlineNodeWriteData::create(
            $current,
            [],
            '3',
            'call_forward',
            ['action' => 'update', 'skip_module' => false],
        );
    }

    public function test_it_rejects_acdc_agent_writes(): void
    {
        $current = [
            'flow' => [
                'module' => 'menu',
                'data' => ['id' => 'menu-1'],
                'children' => [],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inline Switch callflow action is not supported');
        CallflowInlineNodeWriteData::create(
            $current,
            [],
            '1',
            'acdc_agent',
            ['action' => 'login', 'skip_module' => false],
        );
    }

    public function test_it_preserves_existing_acdc_agent_data_and_locks_its_subtree(): void
    {
        $agentAction = [
            'module' => 'acdc_agent',
            'data' => [
                'action' => 'paused',
                'presence_id' => 'raw-presence-id',
                'timeout' => 999999,
                'future_option' => ['preserve' => true],
            ],
            'children' => [
                '_' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []],
            ],
        ];
        $current = [
            'flow' => [
                'module' => 'menu',
                'data' => ['id' => 'menu-1'],
                'children' => [
                    '1' => $agentAction,
                    '2' => ['module' => 'hangup', 'data' => ['skip_module' => false], 'children' => []],
                ],
            ],
        ];

        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['2'],
            'hangup',
            ['skip_module' => true],
        )->toSwitchData();
        $children = (array) $updated['flow']['children'];

        self::assertSame($agentAction['data'], $children['1']['data']);
        self::assertSame('user', ((array) $children['1']['children'])['_']['module']);
        self::assertTrue($children['2']['data']['skip_module']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('preserved branches that cannot be edited');
        CallflowInlineNodeWriteData::create(
            $current,
            ['1'],
            '2',
            'hangup',
            ['skip_module' => false],
        );
    }

    public function test_it_rejects_eavesdrop_family_writes_and_preserves_private_subtrees(): void
    {
        $eavesdrop = [
            'module' => 'eavesdrop',
            'data' => [
                'approved_group_id' => 'raw-approved-group-id',
                'device_id' => 'raw-target-device-id',
                'future_option' => ['preserve' => true],
            ],
            'children' => [
                '_' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []],
            ],
        ];
        $eavesdropFeature = [
            'module' => 'eavesdrop_feature',
            'data' => [
                'approved_user_id' => 'raw-approved-user-id',
                'group_id' => 'raw-target-group-id',
                'future_option' => ['preserve' => true],
            ],
            'children' => [
                '_' => ['module' => 'device', 'data' => ['id' => 'device-1'], 'children' => []],
            ],
        ];
        $current = [
            'flow' => [
                'module' => 'menu',
                'data' => ['id' => 'menu-1'],
                'children' => [
                    '1' => $eavesdrop,
                    '2' => $eavesdropFeature,
                    '3' => ['module' => 'hangup', 'data' => ['skip_module' => false], 'children' => []],
                ],
            ],
        ];

        $updated = CallflowInlineNodeWriteData::update(
            $current,
            ['3'],
            'hangup',
            ['skip_module' => true],
        )->toSwitchData();
        $children = (array) $updated['flow']['children'];

        self::assertSame($eavesdrop['data'], $children['1']['data']);
        self::assertSame(
            'user',
            ((array) $children['1']['children'])['_']['module'],
        );
        self::assertSame($eavesdropFeature['data'], $children['2']['data']);
        self::assertSame(
            'device',
            ((array) $children['2']['children'])['_']['module'],
        );
        self::assertTrue($children['3']['data']['skip_module']);

        foreach ([['1'], ['2']] as $path) {
            try {
                CallflowInlineNodeWriteData::create(
                    $current,
                    $path,
                    '1',
                    'hangup',
                    ['skip_module' => false],
                );
                self::fail('Expected the Eavesdrop subtree to remain locked.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(
                    'This conditional action has preserved branches that cannot be edited.',
                    $exception->getMessage(),
                );
            }
        }

        foreach (['eavesdrop', 'eavesdrop_feature'] as $module) {
            try {
                CallflowInlineNodeWriteData::create($current, [], '4', $module, []);
                self::fail('Expected the Eavesdrop action to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(
                    'The inline Switch callflow action is not supported.',
                    $exception->getMessage(),
                );
            }
        }
    }

    /** @throws JsonException */
    public function test_it_updates_inline_recording_settings_without_exposing_server_owned_storage_data(): void
    {
        $client = $this->clientWithResponse([
            'id' => 'callflow-1',
            'flow' => ['module' => 'menu', 'data' => ['id' => 'menu-1'], 'children' => []],
        ]);
        $current = [
            'flow' => [
                'module' => 'menu',
                'data' => ['id' => 'menu-1'],
                'children' => [
                    '_' => [
                        'module' => 'record_call',
                        'data' => [
                            'action' => 'start',
                            'format' => 'mp3',
                            'url' => 'https://storage.internal.example/recordings',
                            'method' => 'put',
                            'origin' => 'server-owned',
                            'vendor_option' => ['preserve' => true],
                        ],
                        'children' => [
                            '_' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []],
                        ],
                    ],
                ],
            ],
        ];

        $client->writeInlineTreeNode(
            'account-1',
            'callflow-1',
            CallflowInlineNodeWriteData::update(
                $current,
                ['_'],
                'record_call',
                [
                    'action' => 'start',
                    'format' => 'wav',
                    'label' => 'Support recording',
                    'record_min_sec' => 2,
                    'record_on_answer' => true,
                    'record_on_bridge' => false,
                    'record_sample_rate' => 16000,
                    'should_follow_transfer' => true,
                    'time_limit' => 1800,
                    'skip_module' => false,
                ],
            ),
        );

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $node = $body['data']['flow']['children']['_'];

        self::assertSame('wav', $node['data']['format']);
        self::assertSame('https://storage.internal.example/recordings', $node['data']['url']);
        self::assertSame('put', $node['data']['method']);
        self::assertSame('server-owned', $node['data']['origin']);
        self::assertTrue($node['data']['vendor_option']['preserve']);
        self::assertSame('user-1', $node['children']['_']['data']['id']);
    }

    public function test_it_builds_current_schema_dtmf_language_and_alert_inline_actions(): void
    {
        $fixtures = [
            'send_dtmf' => ['digits' => '1234#', 'duration_ms' => 2000, 'skip_module' => false],
            'flush_dtmf' => ['collection_name' => 'default', 'skip_module' => false],
            'dead_air' => ['skip_module' => false],
            'language' => ['language' => 'en-US', 'skip_module' => false],
            'response' => ['code' => 486, 'message' => 'Busy here', 'skip_module' => false],
            'hangup' => ['skip_module' => false],
            'set_variable' => [
                'variable' => 'call_priority',
                'value' => '6',
                'channel' => 'a',
                'skip_module' => false,
            ],
            'missed_call_alert' => [
                'recipients' => [
                    ['type' => 'user', 'id' => 'switch-user-reception'],
                    ['type' => 'email', 'id' => 'alerts@example.com'],
                ],
                'skip_module' => false,
            ],
            'set_cid' => [
                'caller_id_name' => 'Support',
                'caller_id_number' => '+15551234567',
                'skip_module' => false,
            ],
            'prepend_cid' => [
                'action' => 'prepend',
                'apply_to' => 'original',
                'caller_id_name_prefix' => 'Sales ',
                'caller_id_number_prefix' => '9',
                'skip_module' => false,
            ],
            'set_alert_info' => ['alert_info' => 'Bellcore-dr2', 'skip_module' => false],
            'check_cid' => [
                'regex' => '^\\+1555',
                'use_absolute_mode' => false,
                'caller_id' => [
                    'external' => ['name' => 'Support', 'number' => '+15551234567'],
                ],
                'user_id' => 'switch-user-reception',
                'skip_module' => false,
            ],
            'cidlistmatch' => [
                'id' => 'switch-list-vip',
                'skip_module' => false,
            ],
        ];

        foreach ($fixtures as $module => $settings) {
            $document = CallflowInlineNodeWriteData::create(
                [
                    'flow' => [
                        'module' => 'user',
                        'data' => ['id' => 'switch-user-parent'],
                        'children' => [],
                    ],
                ],
                [],
                '_',
                $module,
                $settings,
            )->toSwitchData();

            $children = (array) $document['flow']['children'];
            self::assertSame($module, $children['_']['module']);
            self::assertSame($settings, $children['_']['data']);
        }
    }

    public function test_it_inserts_a_non_terminal_inline_action_before_an_occupied_continuation(): void
    {
        $document = CallflowInlineNodeWriteData::create(
            [
                'flow' => [
                    'module' => 'set_variables',
                    'data' => ['custom_application_vars' => ['department' => 'support']],
                    'children' => [
                        '_' => [
                            'module' => 'device',
                            'data' => ['id' => 'device-1'],
                            'children' => [],
                        ],
                    ],
                ],
            ],
            [],
            '_',
            'tts',
            [
                'text' => 'Please wait while we connect you.',
                'voice' => null,
                'language' => 'en-US',
                'engine' => null,
                'endless_playback' => false,
                'terminators' => ['#'],
                'skip_module' => false,
            ],
            'insert_before',
        )->toSwitchData();

        $inserted = ((array) $document['flow']['children'])['_'];
        self::assertSame('tts', $inserted['module']);
        self::assertSame('device-1', ((array) $inserted['children'])['_']['data']['id']);
    }

    public function test_it_atomically_replaces_an_occupied_continuation_with_a_terminal_action(): void
    {
        $document = CallflowInlineNodeWriteData::create(
            [
                'flow' => [
                    'module' => 'set_variables',
                    'data' => ['custom_application_vars' => ['department' => 'support']],
                    'children' => [
                        '_' => [
                            'module' => 'device',
                            'data' => ['id' => 'device-1'],
                            'children' => [],
                        ],
                    ],
                ],
            ],
            [],
            '_',
            'response',
            ['code' => 486, 'message' => 'Busy here', 'skip_module' => false],
            'replace',
        )->toSwitchData();

        $replacement = ((array) $document['flow']['children'])['_'];
        self::assertSame('response', $replacement['module']);
        self::assertSame(486, $replacement['data']['code']);
        self::assertSame([], (array) $replacement['children']);
    }

    public function test_it_rejects_inserting_a_terminal_action_before_an_existing_subtree(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('terminal action cannot preserve');

        CallflowInlineNodeWriteData::create(
            [
                'flow' => [
                    'module' => 'set_variables',
                    'data' => [],
                    'children' => [
                        '_' => ['module' => 'device', 'data' => ['id' => 'device-1'], 'children' => []],
                    ],
                ],
            ],
            [],
            '_',
            'response',
            ['code' => 486, 'message' => null, 'skip_module' => false],
            'insert_before',
        );
    }

    public function test_it_rejects_an_invalid_call_language(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('language setting is invalid');

        CallflowInlineNodeWriteData::create(
            ['flow' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []]],
            [],
            '_',
            'language',
            ['language' => 'english', 'skip_module' => false],
        );
    }

    public function test_it_updates_response_fields_while_preserving_server_owned_media_and_children(): void
    {
        $document = CallflowInlineNodeWriteData::update(
            [
                'flow' => [
                    'module' => 'user',
                    'data' => ['id' => 'user-1'],
                    'children' => [
                        '_' => [
                            'module' => 'response',
                            'data' => [
                                'code' => 486,
                                'message' => 'Busy here',
                                'media' => 'switch-media-private',
                                'skip_module' => false,
                            ],
                            'children' => [
                                '_' => [
                                    'module' => 'user',
                                    'data' => ['id' => 'user-2'],
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            ['_'],
            'response',
            ['code' => 603, 'message' => null, 'skip_module' => false],
        )->toSwitchData();

        $rootChildren = (array) $document['flow']['children'];
        $node = $rootChildren['_'];
        self::assertSame(603, $node['data']['code']);
        self::assertArrayNotHasKey('message', $node['data']);
        self::assertSame('switch-media-private', $node['data']['media']);
        $children = (array) $node['children'];
        self::assertSame('user-2', $children['_']['data']['id']);
    }

    public function test_it_rejects_an_invalid_response_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('code setting is invalid');

        CallflowInlineNodeWriteData::create(
            ['flow' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []]],
            [],
            '_',
            'response',
            ['code' => 399, 'message' => null, 'skip_module' => false],
        );
    }

    public function test_it_rejects_arbitrary_or_out_of_range_channel_variables(): void
    {
        $base = ['flow' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []]];

        foreach ([
            ['variable' => 'sip_h_X-Unsafe', 'value' => '6', 'channel' => 'a', 'skip_module' => false],
            ['variable' => 'call_priority', 'value' => '256', 'channel' => 'a', 'skip_module' => false],
        ] as $settings) {
            try {
                CallflowInlineNodeWriteData::create($base, [], '_', 'set_variable', $settings);
                self::fail('Unsafe channel variable settings must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_it_refuses_to_rewrite_an_existing_unsupported_channel_variable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('existing inline channel variable is not supported');

        CallflowInlineNodeWriteData::update(
            [
                'flow' => [
                    'module' => 'user',
                    'data' => ['id' => 'user-1'],
                    'children' => [
                        '_' => [
                            'module' => 'set_variable',
                            'data' => ['variable' => 'legacy_custom', 'value' => 'secret'],
                            'children' => [],
                        ],
                    ],
                ],
            ],
            ['_'],
            'set_variable',
            ['variable' => 'call_priority', 'value' => '6', 'channel' => 'a', 'skip_module' => false],
        );
    }

    public function test_it_rejects_alert_info_header_injection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('alert_info setting is invalid');

        CallflowInlineNodeWriteData::create(
            ['flow' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []]],
            [],
            '_',
            'set_alert_info',
            ['alert_info' => "Bellcore-dr2\r\nX-Injected: yes", 'skip_module' => false],
        );
    }

    public function test_it_rejects_unsafe_or_absolute_caller_id_checks(): void
    {
        $settings = [
            'regex' => '(?R)',
            'use_absolute_mode' => false,
            'caller_id' => null,
            'user_id' => null,
            'skip_module' => false,
        ];

        try {
            CallflowInlineNodeWriteData::create(
                ['flow' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []]],
                [],
                '_',
                'check_cid',
                $settings,
            );
            self::fail('Unsafe regular expressions must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('check mode is invalid', $exception->getMessage());
        }

        $settings['regex'] = '.*';
        $settings['use_absolute_mode'] = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('check mode is invalid');

        CallflowInlineNodeWriteData::create(
            ['flow' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []]],
            [],
            '_',
            'check_cid',
            $settings,
        );
    }

    public function test_it_preserves_absolute_caller_id_nodes_as_read_only(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Absolute-mode caller ID checks are preserved');

        CallflowInlineNodeWriteData::update(
            [
                'flow' => [
                    'module' => 'user',
                    'data' => ['id' => 'user-1'],
                    'children' => [
                        '_' => [
                            'module' => 'check_cid',
                            'data' => ['regex' => '.*', 'use_absolute_mode' => true],
                            'children' => [
                                '+15551234567' => ['module' => 'device', 'data' => ['id' => 'device-1']],
                            ],
                        ],
                    ],
                ],
            ],
            ['_'],
            'check_cid',
            [
                'regex' => '.*',
                'use_absolute_mode' => false,
                'caller_id' => null,
                'user_id' => null,
                'skip_module' => false,
            ],
        );
    }

    public function test_it_rejects_fixed_children_under_absolute_caller_id_checks(): void
    {
        $current = [
            'flow' => [
                'module' => 'check_cid',
                'data' => ['regex' => '.*', 'use_absolute_mode' => true],
                'children' => [],
            ],
        ];

        try {
            CallflowTreeNodeWriteData::create($current, [], 'match', 'user', 'user-1');
            self::fail('Absolute-mode checks must reject fixed reference branches.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Absolute-mode caller ID branches', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Absolute-mode caller ID branches');

        CallflowInlineNodeWriteData::create(
            $current,
            [],
            'nomatch',
            'dead_air',
            ['skip_module' => false],
        );
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
                    'numbers' => ['+1001', '+15550000100'],
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

        $rawBody = (string) $this->history[0]['request']->getBody();
        $body = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['1010', '+15550000100'], $body['data']['numbers']);
        self::assertSame(25, $body['data']['flow']['data']['timeout']);
        self::assertSame('voicemail-2', $body['data']['flow']['children']['_']['data']['id']);
        self::assertTrue($body['data']['flow']['children']['busy']['data']['preserve']);
        self::assertArrayNotHasKey('_rev', $body['data']);
        self::assertArrayNotHasKey('pvt_account_id', $body['data']);
        self::assertStringContainsString('"children":{}', $rawBody);
    }

    /** @param array<string, mixed> $responseData */
    private function clientWithResponse(
        array $responseData,
        ?array $authoritativeResponseData = null,
    ): CallflowResourceClient {
        $this->history = [];
        $responseBody = json_encode([
            'status' => 'success',
            'data' => $responseData,
        ], JSON_THROW_ON_ERROR);
        $authoritativeBody = json_encode([
            'status' => 'success',
            'data' => $authoritativeResponseData ?? $responseData,
        ], JSON_THROW_ON_ERROR);
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], $responseBody),
            new Response(200, [], $authoritativeBody),
        ]));
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

                public function invalidate(): void {}
            },
        );

        return new CallflowResourceClient($switch);
    }
}
