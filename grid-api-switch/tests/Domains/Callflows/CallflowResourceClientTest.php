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
            'missed_call_alert' => [
                'recipients' => [
                    ['type' => 'user', 'id' => 'switch-user-reception'],
                    ['type' => 'email', 'id' => 'alerts@example.com'],
                ],
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

                public function invalidate(): void {}
            },
        );

        return new CallflowResourceClient($switch);
    }
}
