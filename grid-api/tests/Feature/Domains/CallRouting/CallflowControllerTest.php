<?php

namespace Tests\Feature\Domains\CallRouting;

use App\Domains\Auditing\Models\AuditLog;
use App\Domains\CallRouting\Contracts\SwitchCallflowGateway;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CallflowControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_lists_filters_and_shows_safe_route_structures_using_public_ids(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Reception',
            'extension' => '1001',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->for($extension, 'extension')->create([
            'name' => 'Main Reception',
            'numbers' => ['1001', '+14155550100'],
            'patterns' => [],
            'modules' => ['ring_group', 'voicemail'],
            'root_module' => 'ring_group',
            'node_count' => 2,
            'max_depth' => 2,
            'flow_structure' => [
                'module' => 'ring_group',
                'children' => [
                    '_' => ['module' => 'voicemail', 'children' => []],
                    'switch-rule-secret' => ['module' => 'custom_vendor', 'children' => []],
                ],
            ],
            'switch_json' => [
                'id' => 'upstream-callflow-id',
                'flow' => ['data' => ['pin' => '[REDACTED]']],
            ],
        ]);
        $phoneNumber = SwitchPhoneNumber::factory()->for($account)->create([
            'assigned_callflow_id' => $callflow->getKey(),
            'number' => '+14155550100',
        ]);
        SwitchCallflow::factory()->for($account)->create([
            'name' => 'Feature code',
            'is_feature_code' => true,
            'feature_code_name' => 'Do Not Disturb',
            'feature_code_number' => '*78',
        ]);
        SyncCheckpoint::query()->create([
            'switch_account_id' => $account->getKey(),
            'resource_type' => 'extensions',
            'status' => ProjectionStatus::Healthy,
            'last_successful_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows?search=Reception&module=voicemail&type=phone_number")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $callflow->id)
            ->assertJsonPath('data.0.route_type', 'phone_number')
            ->assertJsonPath('data.0.root_module', 'ring_group')
            ->assertJsonPath('data.0.linked_extension.id', $extension->id)
            ->assertJsonPath('data.0.phone_numbers.0.id', $phoneNumber->id)
            ->assertJsonPath('data.0.flow.children._.module', 'voicemail')
            ->assertJsonPath('data.0.flow.children._.branch.label', 'Default branch')
            ->assertJsonPath('data.0.flow.children.preserved_1.module', 'custom_vendor')
            ->assertJsonPath('data.0.flow.children.preserved_1.branch.label', 'Preserved branch 1')
            ->assertJsonPath('meta.sync.status', 'healthy')
            ->assertJsonMissingPath('data.0.callflow_id')
            ->assertJsonMissingPath('data.0.switch_resource_id')
            ->assertJsonMissingPath('data.0.switch_json')
            ->assertJsonMissingPath('data.0.flow.data');
        $this->assertStringNotContainsString('switch-rule-secret', $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}")
            ->getContent());

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}")
            ->assertOk()
            ->assertJsonPath('data.node_count', 2)
            ->assertJsonPath('data.max_depth', 2);
    }

    public function test_it_filters_feature_codes_and_hides_routes_from_other_accounts(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $featureCode = SwitchCallflow::factory()->for($account)->create([
            'name' => null,
            'is_feature_code' => true,
            'feature_code_name' => 'Do Not Disturb',
            'feature_code_number' => '*78',
        ]);
        $otherCallflow = SwitchCallflow::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows?type=feature_code")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $featureCode->id)
            ->assertJsonPath('data.0.feature_code.number', '*78');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/{$otherCallflow->id}")
            ->assertNotFound();
    }

    public function test_it_exposes_public_editor_options_and_updates_a_resolved_destination(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-reception',
            'display_name' => 'Reception',
            'extension' => '1001',
        ]);
        $voicemail = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-mailbox-reception',
            'name' => 'Reception fallback',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'switch-callflow-main',
            'name' => 'Main route',
            'numbers' => ['18005550100'],
            'flow_structure' => [
                'module' => 'user',
                'target' => [
                    'type' => 'extension',
                    'id' => $extension->id,
                    'label' => 'Reception',
                ],
                'reference_status' => 'resolved',
                'children' => [
                    '_' => [
                        'module' => 'voicemail',
                        'target' => [
                            'type' => 'voicemail',
                            'id' => $voicemail->id,
                            'label' => 'Reception fallback',
                        ],
                        'reference_status' => 'resolved',
                        'children' => [],
                    ],
                ],
            ],
        ]);
        $currentlyAssigned = SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15550000100',
            'assigned_callflow_id' => $callflow->getKey(),
        ]);
        $newPhoneNumber = SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15550000200',
            'assigned_callflow_id' => null,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/editor")
            ->assertOk()
            ->assertJsonPath('data.editable', true)
            ->assertJsonPath('data.fallback.editable', true)
            ->assertJsonPath('data.fallback.target.id', $voicemail->id)
            ->assertJsonPath('data.destinations.extension.0.id', $extension->id)
            ->assertJsonPath('data.destinations.extension.0.label', 'Reception')
            ->assertJsonPath('data.phone_numbers.0.id', $currentlyAssigned->id)
            ->assertJsonPath('data.phone_numbers.0.selected', true)
            ->assertJsonPath('data.phone_numbers.1.id', $newPhoneNumber->id)
            ->assertJsonPath('data.phone_numbers.1.available', true)
            ->assertJsonMissing(['switch-user-reception']);

        $gateway = new class implements SwitchCallflowGateway
        {
            /** @var array<string, mixed> */
            public array $received = [];

            public function create(
                SwitchAccount $account,
                string $name,
                string $destinationModule,
                ?string $destinationResourceId,
                array $phoneNumbers,
                ?string $fallbackModule = null,
                ?string $fallbackResourceId = null,
                array $menuBranches = [],
                array $destinationTemporalRuleIds = [],
            ): array {
                throw new \LogicException('Not used by this test.');
            }

            public function updateDestination(
                SwitchAccount $account,
                string $resourceId,
                string $destinationModule,
                ?string $destinationResourceId,
                ?string $name,
                array $assignedPhoneNumbers,
                array $knownPhoneNumbers,
                bool $replaceFallback = false,
                ?string $fallbackModule = null,
                ?string $fallbackResourceId = null,
                array $menuBranchOperations = [],
                array $destinationTemporalRuleIds = [],
            ): array {
                $this->received = compact(
                    'resourceId',
                    'destinationModule',
                    'destinationResourceId',
                    'name',
                    'assignedPhoneNumbers',
                    'knownPhoneNumbers',
                );

                return [
                    'id' => $resourceId,
                    'name' => $name,
                    'numbers' => ['1001', ...$assignedPhoneNumbers],
                    'patterns' => [],
                    'flow' => [
                        'module' => $destinationModule,
                        'data' => ['id' => $destinationResourceId],
                        'children' => [
                            '_' => [
                                'module' => 'custom_vendor_module',
                                'data' => ['private_upstream_value' => 'server-only'],
                                'children' => [],
                            ],
                        ],
                    ],
                ];
            }

            public function delete(SwitchAccount $account, string $resourceId): void
            {
                throw new \LogicException('Not used by this test.');
            }

            public function moveTreeNode(SwitchAccount $account, string $resourceId, array $sourcePath, array $destinationParentPath, string $destinationBranch): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function writeTreeNode(SwitchAccount $account, string $resourceId, string $operation, array $path, ?string $branch, string $module, string $targetResourceId): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function reorderTreeNodes(SwitchAccount $account, string $resourceId, string $mode, array $sourcePath, array $targetPath): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function writeInlineTreeNode(SwitchAccount $account, string $resourceId, string $operation, array $path, ?string $branch, string $module, array $settings): array
            {
                throw new \LogicException('Not used by this test.');
            }
        };
        $this->app->instance(SwitchCallflowGateway::class, $gateway);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}", [
                'name' => 'Reception route',
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
                'phone_number_ids' => [$newPhoneNumber->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Reception route')
            ->assertJsonPath('data.root_module', 'user')
            ->assertJsonPath('data.flow.target.type', 'extension')
            ->assertJsonPath('data.flow.target.id', $extension->id)
            ->assertJsonPath('data.flow.reference_status', 'resolved')
            ->assertJsonPath('data.flow.children._.module', 'custom_vendor_module')
            ->assertJsonPath('data.phone_numbers.0.id', $newPhoneNumber->id)
            ->assertJsonMissing(['switch-user-reception'])
            ->assertJsonMissing(['server-only']);

        $this->assertSame('switch-user-reception', $gateway->received['destinationResourceId']);
        $this->assertSame('user', $gateway->received['destinationModule']);
        $this->assertSame(['+15550000200'], $gateway->received['assignedPhoneNumbers']);
        $this->assertEqualsCanonicalizing(
            ['+15550000100', '+15550000200'],
            $gateway->received['knownPhoneNumbers'],
        );
        $this->assertNull($currentlyAssigned->fresh()->assigned_callflow_id);
        $this->assertSame($callflow->getKey(), $newPhoneNumber->fresh()->assigned_callflow_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'callflow.updated',
            'resource_type' => 'callflow',
            'outcome' => 'succeeded',
        ]);
        $this->assertSame(
            $extension->id,
            AuditLog::query()->latest('created_at')->firstOrFail()->metadata['destination_id'],
        );
    }

    public function test_it_moves_a_public_callflow_subtree_and_rejects_unsafe_paths(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $menu = SwitchMenu::factory()->for($account)->create([
            'switch_resource_id' => 'switch-menu-main',
            'name' => 'Main menu',
        ]);
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-reception',
            'display_name' => 'Reception',
            'extension' => '1001',
        ]);
        $group = SwitchGroup::factory()->for($account)->create([
            'switch_resource_id' => 'switch-group-support',
            'name' => 'Support',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'switch-callflow-main',
            'name' => 'Main route',
            'root_module' => 'menu',
            'modules' => ['menu', 'user', 'group'],
            'node_count' => 3,
            'max_depth' => 2,
            'flow_structure' => [
                'module' => 'menu',
                'target' => ['type' => 'menu', 'id' => $menu->id, 'label' => 'Main menu'],
                'reference_status' => 'resolved',
                'children' => [
                    '1' => [
                        'module' => 'user',
                        'target' => ['type' => 'extension', 'id' => $extension->id, 'label' => 'Reception'],
                        'reference_status' => 'resolved',
                        'children' => [],
                    ],
                    '2' => [
                        'module' => 'group',
                        'target' => ['type' => 'group', 'id' => $group->id, 'label' => 'Support'],
                        'reference_status' => 'resolved',
                        'children' => [],
                    ],
                ],
            ],
        ]);

        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldReceive('moveTreeNode')->once()->withArgs(fn (
            SwitchAccount $receivedAccount,
            string $resourceId,
            array $sourcePath,
            array $destinationParentPath,
            string $destinationBranch,
        ): bool => $receivedAccount->is($account)
            && $resourceId === 'switch-callflow-main'
            && $sourcePath === ['1']
            && $destinationParentPath === ['2']
            && $destinationBranch === '_')->andReturn([
                'id' => 'switch-callflow-main',
                'name' => 'Main route',
                'numbers' => [],
                'patterns' => [],
                'flow' => [
                    'module' => 'menu',
                    'data' => ['id' => 'switch-menu-main'],
                    'children' => [
                        '2' => [
                            'module' => 'group',
                            'data' => ['id' => 'switch-group-support'],
                            'children' => [
                                '_' => [
                                    'module' => 'user',
                                    'data' => ['id' => 'switch-user-reception'],
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree", [
                'source_path' => ['1'],
                'destination_parent_path' => ['2'],
                'destination_branch' => '_',
            ])
            ->assertOk()
            ->assertJsonMissingPath('data.flow.children.1')
            ->assertJsonPath('data.flow.children.2.children._.target.id', $extension->id)
            ->assertJsonMissing(['switch-user-reception']);

        $this->assertDatabaseHas('switch_callflows', [
            'callflow_id' => $callflow->getKey(),
            'node_count' => 3,
            'max_depth' => 3,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'callflow.node_moved',
            'outcome' => 'succeeded',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree", [
                'source_path' => ['preserved_1'],
                'destination_parent_path' => [],
                'destination_branch' => '_',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_path.0');

        $this->actingAs($user)
            ->patchJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree", [
                'source_path' => ['2', '_'],
                'destination_parent_path' => ['2'],
                'destination_branch' => '_',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destination_branch');

        $callflow->refresh();
        $flow = $callflow->flow_structure;
        $flow['children']['3'] = [
            'module' => 'custom_vendor',
            'target' => null,
            'reference_status' => 'not_applicable',
            'children' => [],
        ];
        $callflow->forceFill(['flow_structure' => $flow])->save();

        $this->actingAs($user)
            ->patchJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree", [
                'source_path' => ['3'],
                'destination_parent_path' => ['2'],
                'destination_branch' => '_',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_path');
    }

    public function test_it_reorders_guided_subtrees_using_only_public_paths(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $menu = SwitchMenu::factory()->for($account)->create([
            'switch_resource_id' => 'switch-menu-main',
            'name' => 'Main menu',
        ]);
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-reception',
            'display_name' => 'Reception',
            'extension' => '1001',
        ]);
        $group = SwitchGroup::factory()->for($account)->create([
            'switch_resource_id' => 'switch-group-support',
            'name' => 'Support',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'switch-callflow-main',
            'name' => 'Main route',
            'flow_structure' => [
                'module' => 'menu',
                'target' => ['type' => 'menu', 'id' => $menu->id, 'label' => 'Main menu'],
                'reference_status' => 'resolved',
                'children' => [
                    '1' => [
                        'module' => 'user',
                        'target' => ['type' => 'extension', 'id' => $extension->id, 'label' => 'Reception'],
                        'reference_status' => 'resolved',
                        'children' => [],
                    ],
                    '2' => [
                        'module' => 'group',
                        'target' => ['type' => 'group', 'id' => $group->id, 'label' => 'Support'],
                        'reference_status' => 'resolved',
                        'children' => [],
                    ],
                ],
            ],
        ]);

        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldReceive('reorderTreeNodes')->once()->withArgs(fn (
            SwitchAccount $receivedAccount,
            string $resourceId,
            string $mode,
            array $sourcePath,
            array $targetPath,
        ): bool => $receivedAccount->is($account)
            && $resourceId === 'switch-callflow-main'
            && $mode === 'swap'
            && $sourcePath === ['1']
            && $targetPath === ['2'])->andReturn([
                'id' => 'switch-callflow-main',
                'name' => 'Main route',
                'numbers' => [],
                'patterns' => [],
                'flow' => [
                    'module' => 'menu',
                    'data' => ['id' => 'switch-menu-main'],
                    'children' => [
                        '1' => [
                            'module' => 'group',
                            'data' => ['id' => 'switch-group-support'],
                            'children' => [],
                        ],
                        '2' => [
                            'module' => 'user',
                            'data' => ['id' => 'switch-user-reception'],
                            'children' => [],
                        ],
                    ],
                ],
            ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree/order", [
                'mode' => 'swap',
                'source_path' => ['1'],
                'target_path' => ['2'],
            ])
            ->assertOk()
            ->assertJsonPath('data.flow.children.1.target.id', $group->id)
            ->assertJsonPath('data.flow.children.2.target.id', $extension->id)
            ->assertJsonMissing(['switch-group-support', 'switch-user-reception']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'callflow.nodes_reordered',
            'outcome' => 'succeeded',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree/order", [
                'mode' => 'swap',
                'source_path' => ['1'],
                'target_path' => ['1'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_path');
    }

    public function test_it_adds_schema_backed_inline_nodes_and_exposes_only_safe_settings(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $menu = SwitchMenu::factory()->for($account)->create([
            'switch_resource_id' => 'switch-menu-main',
            'name' => 'Main menu',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'switch-callflow-main',
            'flow_structure' => [
                'module' => 'menu',
                'target' => ['type' => 'menu', 'id' => $menu->id, 'label' => 'Main menu'],
                'reference_status' => 'resolved',
                'children' => [],
            ],
        ]);
        $settings = [
            'text' => 'Welcome to GridPBX.',
            'voice' => 'female',
            'language' => 'en-US',
            'engine' => 'flite',
            'endless_playback' => false,
            'terminators' => ['#'],
            'skip_module' => false,
        ];
        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldReceive('writeInlineTreeNode')->once()->withArgs(fn (
            SwitchAccount $receivedAccount,
            string $resourceId,
            string $operation,
            array $path,
            ?string $branch,
            string $module,
            array $receivedSettings,
        ): bool => $receivedAccount->is($account)
            && $resourceId === 'switch-callflow-main'
            && $operation === 'create'
            && $path === []
            && $branch === '_'
            && $module === 'tts'
            && $receivedSettings === $settings)->andReturn([
                'id' => 'switch-callflow-main',
                'name' => 'Main route',
                'numbers' => [],
                'patterns' => [],
                'flow' => [
                    'module' => 'menu',
                    'data' => ['id' => 'switch-menu-main'],
                    'children' => [
                        '_' => [
                            'module' => 'tts',
                            'data' => [...$settings, 'internal_provider_token' => 'server-secret'],
                            'children' => [],
                        ],
                    ],
                ],
            ]);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree/inline-nodes", [
                'parent_path' => [],
                'branch' => '_',
                'module' => 'tts',
                'data' => $settings,
            ])
            ->assertOk()
            ->assertJsonPath('data.flow.children._.module', 'tts')
            ->assertJsonPath('data.flow.children._.settings.text', 'Welcome to GridPBX.')
            ->assertJsonPath('data.flow.children._.settings.engine', 'flite')
            ->assertJsonMissing(['server-secret']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'callflow.inline_node_created',
            'outcome' => 'succeeded',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree/inline-nodes", [
                'node_path' => ['_'],
                'module' => 'tts',
                'data' => [...$settings, 'url' => 'https://should-not-be-user-managed.example'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data');
    }

    public function test_it_maps_missed_call_alert_recipients_across_the_public_switch_boundary(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $menu = SwitchMenu::factory()->for($account)->create([
            'switch_resource_id' => 'switch-menu-main',
            'name' => 'Main menu',
        ]);
        $recipient = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-reception',
            'display_name' => 'Reception',
            'extension' => '1001',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'switch-callflow-main',
            'flow_structure' => [
                'module' => 'menu',
                'target' => ['type' => 'menu', 'id' => $menu->id, 'label' => 'Main menu'],
                'reference_status' => 'resolved',
                'children' => [],
            ],
        ]);
        $publicSettings = [
            'recipients' => [
                ['type' => 'user', 'id' => $recipient->id],
                ['type' => 'email', 'id' => 'alerts@example.com'],
            ],
            'skip_module' => false,
        ];
        $switchSettings = [
            'recipients' => [
                ['type' => 'user', 'id' => 'switch-user-reception'],
                ['type' => 'email', 'id' => 'alerts@example.com'],
            ],
            'skip_module' => false,
        ];
        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldReceive('writeInlineTreeNode')->once()->withArgs(fn (
            SwitchAccount $receivedAccount,
            string $resourceId,
            string $operation,
            array $path,
            ?string $branch,
            string $module,
            array $settings,
        ): bool => $receivedAccount->is($account)
            && $resourceId === 'switch-callflow-main'
            && $operation === 'create'
            && $path === []
            && $branch === '_'
            && $module === 'missed_call_alert'
            && $settings === $switchSettings)->andReturn([
                'id' => 'switch-callflow-main',
                'name' => 'Main route',
                'numbers' => [],
                'patterns' => [],
                'flow' => [
                    'module' => 'menu',
                    'data' => ['id' => 'switch-menu-main'],
                    'children' => [
                        '_' => [
                            'module' => 'missed_call_alert',
                            'data' => [...$switchSettings, 'private_notification_token' => 'secret'],
                            'children' => [],
                        ],
                    ],
                ],
            ]);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree/inline-nodes", [
                'parent_path' => [],
                'branch' => '_',
                'module' => 'missed_call_alert',
                'data' => $publicSettings,
            ])
            ->assertOk()
            ->assertJsonPath('data.flow.children._.settings.recipients.0.type', 'user')
            ->assertJsonPath('data.flow.children._.settings.recipients.0.id', $recipient->id)
            ->assertJsonPath('data.flow.children._.settings.recipients.1.id', 'alerts@example.com')
            ->assertJsonMissing(['switch-user-reception', 'secret']);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree/inline-nodes", [
                'parent_path' => [],
                'branch' => '_',
                'module' => 'missed_call_alert',
                'data' => [
                    'recipients' => [['type' => 'user', 'id' => fake()->uuid()]],
                    'skip_module' => false,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.recipients.0.id');
    }

    public function test_it_adds_and_retargets_guided_reference_nodes_without_exposing_switch_ids(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $menu = SwitchMenu::factory()->for($account)->create([
            'switch_resource_id' => 'switch-menu-main',
            'name' => 'Main menu',
        ]);
        $reception = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-reception',
            'display_name' => 'Reception',
            'extension' => '1001',
        ]);
        $support = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-support',
            'display_name' => 'Support',
            'extension' => '1002',
        ]);
        $voicemail = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-mailbox-reception',
            'name' => 'Reception mailbox',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'switch-callflow-main',
            'name' => 'Main route',
            'root_module' => 'menu',
            'modules' => ['menu', 'user'],
            'node_count' => 2,
            'max_depth' => 2,
            'flow_structure' => [
                'module' => 'menu',
                'target' => ['type' => 'menu', 'id' => $menu->id, 'label' => 'Main menu'],
                'reference_status' => 'resolved',
                'children' => [
                    '1' => [
                        'module' => 'user',
                        'target' => ['type' => 'extension', 'id' => $reception->id, 'label' => 'Reception'],
                        'reference_status' => 'resolved',
                        'children' => [],
                    ],
                ],
            ],
        ]);

        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldReceive('writeTreeNode')->once()->ordered()->withArgs(fn (
            SwitchAccount $receivedAccount,
            string $resourceId,
            string $operation,
            array $path,
            ?string $branch,
            string $module,
            string $targetResourceId,
        ): bool => $receivedAccount->is($account)
            && $resourceId === 'switch-callflow-main'
            && $operation === 'create'
            && $path === ['1']
            && $branch === '_'
            && $module === 'voicemail'
            && $targetResourceId === 'switch-mailbox-reception')->andReturn([
                'id' => 'switch-callflow-main',
                'name' => 'Main route',
                'numbers' => [],
                'patterns' => [],
                'flow' => [
                    'module' => 'menu',
                    'data' => ['id' => 'switch-menu-main'],
                    'children' => [
                        '1' => [
                            'module' => 'user',
                            'data' => ['id' => 'switch-user-reception'],
                            'children' => [
                                '_' => [
                                    'module' => 'voicemail',
                                    'data' => ['id' => 'switch-mailbox-reception'],
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        $gateway->shouldReceive('writeTreeNode')->once()->ordered()->withArgs(fn (
            SwitchAccount $receivedAccount,
            string $resourceId,
            string $operation,
            array $path,
            ?string $branch,
            string $module,
            string $targetResourceId,
        ): bool => $receivedAccount->is($account)
            && $resourceId === 'switch-callflow-main'
            && $operation === 'update'
            && $path === ['1']
            && $branch === null
            && $module === 'user'
            && $targetResourceId === 'switch-user-support')->andReturn([
                'id' => 'switch-callflow-main',
                'name' => 'Main route',
                'numbers' => [],
                'patterns' => [],
                'flow' => [
                    'module' => 'menu',
                    'data' => ['id' => 'switch-menu-main'],
                    'children' => [
                        '1' => [
                            'module' => 'user',
                            'data' => ['id' => 'switch-user-support'],
                            'children' => [
                                '_' => [
                                    'module' => 'voicemail',
                                    'data' => ['id' => 'switch-mailbox-reception'],
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree/nodes", [
                'parent_path' => ['1'],
                'branch' => '_',
                'destination_type' => 'voicemail',
                'destination_id' => $voicemail->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.flow.children.1.children._.target.id', $voicemail->id)
            ->assertJsonMissing(['switch-mailbox-reception']);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree/nodes", [
                'parent_path' => ['1'],
                'branch' => '_',
                'destination_type' => 'voicemail',
                'destination_id' => $voicemail->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('branch');

        $this->actingAs($user)
            ->patchJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/tree/nodes", [
                'node_path' => ['1'],
                'destination_type' => 'extension',
                'destination_id' => $support->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.flow.children.1.target.id', $support->id)
            ->assertJsonPath('data.flow.children.1.children._.target.id', $voicemail->id)
            ->assertJsonMissing(['switch-user-support']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'callflow.node_created',
            'outcome' => 'succeeded',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'callflow.node_updated',
            'outcome' => 'succeeded',
        ]);
    }

    public function test_it_locks_unsupported_and_unresolved_roots_in_the_guided_editor_and_api(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create();
        $unsupported = SwitchCallflow::factory()->for($account)->create([
            'flow_structure' => [
                'module' => 'branch_variable',
                'target' => null,
                'reference_status' => 'not_applicable',
                'children' => [],
            ],
        ]);
        $unresolved = SwitchCallflow::factory()->for($account)->create([
            'flow_structure' => [
                'module' => 'device',
                'target' => null,
                'reference_status' => 'unresolved',
                'children' => [],
            ],
        ]);
        $lockedFallback = SwitchCallflow::factory()->for($account)->create([
            'flow_structure' => [
                'module' => 'user',
                'target' => [
                    'type' => 'extension',
                    'id' => $extension->id,
                    'label' => 'Extension',
                ],
                'reference_status' => 'resolved',
                'children' => [
                    '_' => [
                        'module' => 'custom_vendor_module',
                        'target' => null,
                        'reference_status' => 'not_applicable',
                        'children' => [],
                    ],
                ],
            ],
        ]);
        $menu = SwitchMenu::factory()->for($account)->create();
        $lockedMenuBranch = SwitchCallflow::factory()->for($account)->create([
            'flow_structure' => [
                'module' => 'menu',
                'target' => ['type' => 'menu', 'id' => $menu->id, 'label' => 'Main IVR'],
                'reference_status' => 'resolved',
                'children' => [
                    '2' => [
                        'module' => 'user',
                        'target' => [
                            'type' => 'extension',
                            'id' => $extension->id,
                            'label' => 'Extension',
                        ],
                        'reference_status' => 'resolved',
                        'children' => [
                            '_' => [
                                'module' => 'custom_vendor_module',
                                'target' => null,
                                'reference_status' => 'not_applicable',
                                'children' => [],
                            ],
                        ],
                    ],
                    '#' => [
                        'module' => 'custom_legacy_module',
                        'target' => null,
                        'reference_status' => 'not_applicable',
                        'children' => [],
                    ],
                ],
            ],
        ]);
        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldNotReceive('updateDestination');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/{$unsupported->id}/editor")
            ->assertOk()
            ->assertJsonPath('data.editable', false)
            ->assertJsonPath(
                'data.blocked_reason',
                'This route uses a root module that is not yet supported by the guided editor. Its Switch configuration is preserved unchanged.',
            );

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/{$unresolved->id}/editor")
            ->assertOk()
            ->assertJsonPath('data.editable', false)
            ->assertJsonPath(
                'data.blocked_reason',
                'This route target is not available in the current projection. Synchronize the related resource before editing it.',
            );

        foreach ([$unsupported, $unresolved] as $callflow) {
            $this->actingAs($user)
                ->putJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}", [
                    'name' => 'Unsafe replacement',
                    'destination_type' => 'extension',
                    'destination_id' => $extension->id,
                    'phone_number_ids' => [],
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('callflow');
        }

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/{$lockedFallback->id}/editor")
            ->assertOk()
            ->assertJsonPath('data.editable', true)
            ->assertJsonPath('data.fallback.editable', false)
            ->assertJsonPath(
                'data.fallback.blocked_reason',
                'The existing fallback uses an unsupported or unresolved target and is preserved unchanged.',
            );

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/callflows/{$lockedFallback->id}", [
                'name' => 'Unsafe fallback replacement',
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
                'manage_fallback' => true,
                'fallback_destination_type' => null,
                'fallback_destination_id' => null,
                'phone_number_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fallback_destination_id');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/{$lockedMenuBranch->id}/editor")
            ->assertOk()
            ->assertJsonPath('data.menu_branches.editable', true)
            ->assertJsonPath('data.menu_branches.branches.3.key', '2')
            ->assertJsonPath('data.menu_branches.branches.3.editable', false)
            ->assertJsonPath('data.menu_branches.legacy_hash_present', true);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/callflows/{$lockedMenuBranch->id}", [
                'name' => 'Unsafe Menu branch replacement',
                'destination_type' => 'menu',
                'destination_id' => $menu->id,
                'manage_menu_branches' => true,
                'menu_branches' => [[
                    'key' => '2',
                    'destination_type' => 'extension',
                    'destination_id' => $extension->id,
                ]],
                'phone_number_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('menu_branches');
    }

    public function test_it_clears_an_editable_wildcard_fallback(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-clear-fallback',
        ]);
        $voicemail = SwitchVoicemailBox::factory()->for($account)->create();
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'switch-callflow-clear-fallback',
            'flow_structure' => [
                'module' => 'user',
                'target' => [
                    'type' => 'extension',
                    'id' => $extension->id,
                    'label' => 'Extension',
                ],
                'reference_status' => 'resolved',
                'children' => [
                    '_' => [
                        'module' => 'voicemail',
                        'target' => [
                            'type' => 'voicemail',
                            'id' => $voicemail->id,
                            'label' => 'Mailbox',
                        ],
                        'reference_status' => 'resolved',
                        'children' => [],
                    ],
                ],
            ],
        ]);
        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldReceive('updateDestination')
            ->once()
            ->withArgs(fn (
                SwitchAccount $receivedAccount,
                string $resourceId,
                string $module,
                string $resourceTarget,
                ?string $name,
                array $assignedNumbers,
                array $knownNumbers,
                bool $replaceFallback,
                ?string $fallbackModule,
                ?string $fallbackResourceId,
            ): bool => $receivedAccount->is($account)
                && $resourceId === 'switch-callflow-clear-fallback'
                && $module === 'user'
                && $resourceTarget === 'switch-user-clear-fallback'
                && $name === 'Route without fallback'
                && $assignedNumbers === []
                && $knownNumbers === []
                && $replaceFallback
                && $fallbackModule === null
                && $fallbackResourceId === null)
            ->andReturn([
                'id' => 'switch-callflow-clear-fallback',
                'name' => 'Route without fallback',
                'numbers' => [],
                'patterns' => [],
                'flow' => [
                    'module' => 'user',
                    'data' => ['id' => 'switch-user-clear-fallback'],
                    'children' => [],
                ],
            ]);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}", [
                'name' => 'Route without fallback',
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
                'manage_fallback' => true,
                'fallback_destination_type' => null,
                'fallback_destination_id' => null,
                'phone_number_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Route without fallback')
            ->assertJsonPath('data.flow.children', []);
    }

    public function test_read_only_users_cannot_update_call_routing(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->getKey(), [
            'role' => OrganizationRole::ReadOnlyUser->value,
        ]);
        $account = SwitchAccount::factory()->for($organization)->create();
        $extension = SwitchExtension::factory()->for($account)->create();
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'flow_structure' => [
                'module' => 'user',
                'target' => [
                    'type' => 'extension',
                    'id' => $extension->id,
                    'label' => 'Extension',
                ],
                'reference_status' => 'resolved',
                'children' => [],
            ],
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}", [
                'name' => 'Blocked route',
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
                'phone_number_ids' => [],
            ])
            ->assertForbidden();
    }

    public function test_it_creates_a_guided_queue_destination_with_the_acdc_member_module(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $queue = SwitchQueue::factory()->for($account)->create(['switch_resource_id' => 'switch-queue-support']);
        $phoneNumber = SwitchPhoneNumber::factory()->for($account)->create(['number' => '+15550000900', 'assigned_callflow_id' => null]);
        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldReceive('create')->once()->withArgs(fn (SwitchAccount $received, string $name, string $module, string $resourceId, array $numbers): bool => $received->is($account) && $name === 'Support line' && $module === 'acdc_member' && $resourceId === 'switch-queue-support' && $numbers === ['+15550000900'])
            ->andReturn([
                'id' => 'switch-callflow-queue', 'name' => 'Support line', 'numbers' => ['+15550000900'],
                'flow' => ['module' => 'acdc_member', 'data' => ['id' => 'switch-queue-support'], 'children' => []],
            ]);

        $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/callflows", [
            'name' => 'Support line', 'destination_type' => 'queue', 'destination_id' => $queue->id,
            'phone_number_ids' => [$phoneNumber->id],
        ])->assertCreated()->assertJsonPath('data.root_module', 'acdc_member')
            ->assertJsonPath('data.flow.target.type', 'queue')->assertJsonPath('data.flow.target.id', $queue->id);
    }

    public function test_it_creates_a_guided_menu_destination_with_the_menu_module(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $menu = SwitchMenu::factory()->for($account)->create(['switch_resource_id' => 'switch-menu-main']);
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-operator',
            'display_name' => 'Operator',
        ]);
        $phoneNumber = SwitchPhoneNumber::factory()->for($account)->create(['number' => '+15550000901', 'assigned_callflow_id' => null]);
        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldReceive('create')->once()->withArgs(fn (
            SwitchAccount $received,
            string $name,
            string $module,
            string $resourceId,
            array $numbers,
            ?string $fallbackModule,
            ?string $fallbackResourceId,
            array $menuBranches,
        ): bool => $received->is($account)
            && $name === 'Main IVR'
            && $module === 'menu'
            && $resourceId === 'switch-menu-main'
            && $numbers === ['+15550000901']
            && $fallbackModule === null
            && $fallbackResourceId === null
            && $menuBranches === [[
                'key' => '0',
                'module' => 'user',
                'resource_id' => 'switch-user-operator',
            ]])
            ->andReturn([
                'id' => 'switch-callflow-menu', 'name' => 'Main IVR', 'numbers' => ['+15550000901'],
                'flow' => [
                    'module' => 'menu',
                    'data' => ['id' => 'switch-menu-main'],
                    'children' => [
                        '0' => [
                            'module' => 'user',
                            'data' => ['id' => 'switch-user-operator'],
                            'children' => [],
                        ],
                    ],
                ],
            ]);

        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/callflows", [
            'name' => 'Main IVR', 'destination_type' => 'menu', 'destination_id' => $menu->id,
            'manage_menu_branches' => true,
            'menu_branches' => [[
                'key' => '0',
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
            ]],
            'phone_number_ids' => [$phoneNumber->id],
        ])->assertCreated()->assertJsonPath('data.root_module', 'menu')
            ->assertJsonPath('data.flow.target.type', 'menu')
            ->assertJsonPath('data.flow.target.id', $menu->id)
            ->assertJsonPath('data.flow.children.0.target.id', $extension->id);
        $this->assertStringContainsString('"children":{"0":', $response->getContent());
    }

    public function test_it_creates_a_guided_temporal_rule_set_destination(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $set = SwitchTemporalRuleSet::factory()->for($account)->create(['switch_resource_id' => 'switch-set-office']);
        $rule = SwitchTemporalRule::factory()->for($account)->create([
            'switch_resource_id' => 'switch-rule-weekdays',
            'name' => 'Weekdays',
        ]);
        $set->rules()->create([
            'switch_temporal_rule_id' => $rule->getKey(),
            'switch_rule_resource_id' => $rule->switch_resource_id,
            'position' => 0,
        ]);
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-reception',
            'display_name' => 'Reception',
        ]);
        $phoneNumber = SwitchPhoneNumber::factory()->for($account)->create(['number' => '+15550000902', 'assigned_callflow_id' => null]);
        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldReceive('create')->once()->withArgs(fn (
            SwitchAccount $received,
            string $name,
            string $module,
            string $resourceId,
            array $numbers,
            ?string $fallbackModule,
            ?string $fallbackResourceId,
            array $branches,
        ): bool => $received->is($account)
            && $name === 'Office schedule'
            && $module === 'temporal_route'
            && $resourceId === 'switch-set-office'
            && $numbers === ['+15550000902']
            && $fallbackModule === null
            && $fallbackResourceId === null
            && $branches === [[
                'key' => 'rule_set',
                'module' => 'user',
                'resource_id' => 'switch-user-reception',
            ]])
            ->andReturn([
                'id' => 'switch-callflow-temporal',
                'name' => 'Office schedule',
                'numbers' => ['+15550000902'],
                'flow' => [
                    'module' => 'temporal_route',
                    'data' => ['rule_set' => 'switch-set-office'],
                    'children' => [
                        'rule_set' => [
                            'module' => 'user',
                            'data' => ['id' => 'switch-user-reception'],
                            'children' => [],
                        ],
                    ],
                ],
            ]);

        $editorResponse = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/editor")
            ->assertOk()
            ->assertJsonPath("data.temporal_rule_sets.{$set->id}.0.id", $rule->id)
            ->assertJsonPath("data.temporal_rule_sets.{$set->id}.0.label", 'Weekdays')
            ->assertJsonPath("data.temporal_rule_sets.{$set->id}.0.position", 0)
            ->assertJsonPath('data.temporal_match.editable', true);
        $this->assertStringNotContainsString('switch-rule-weekdays', $editorResponse->getContent());
        $this->assertStringNotContainsString('switch-set-office', $editorResponse->getContent());

        $emptySet = SwitchTemporalRuleSet::factory()->for($account)->create([
            'switch_resource_id' => 'switch-set-empty',
        ]);
        $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/callflows", [
            'name' => 'Empty schedule',
            'destination_type' => 'temporal_rule_set',
            'destination_id' => $emptySet->id,
            'manage_temporal_match' => true,
            'temporal_match_destination_type' => 'extension',
            'temporal_match_destination_id' => $extension->id,
            'phone_number_ids' => [$phoneNumber->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('destination_id')
            ->assertJsonPath(
                'errors.destination_id.0',
                'Synchronize a schedule with at least one resolved rule before routing calls through it.',
            );

        $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/callflows", [
            'name' => 'Office schedule',
            'destination_type' => 'temporal_rule_set',
            'destination_id' => $set->id,
            'manage_temporal_match' => true,
            'temporal_match_destination_type' => 'extension',
            'temporal_match_destination_id' => $extension->id,
            'phone_number_ids' => [$phoneNumber->id],
        ])->assertCreated()
            ->assertJsonPath('data.root_module', 'temporal_route')
            ->assertJsonPath('data.flow.target.type', 'temporal_rule_set')
            ->assertJsonPath('data.flow.target.id', $set->id)
            ->assertJsonPath('data.flow.children.rule_set.target.id', $extension->id)
            ->assertJsonPath('data.flow.children.rule_set.branch.label', 'Schedule matches');
    }

    public function test_it_creates_reorders_and_clears_direct_temporal_rule_routes_with_public_ids(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $weekday = SwitchTemporalRule::factory()->for($account)->create([
            'switch_resource_id' => 'switch-rule-weekday',
            'name' => 'Weekday',
        ]);
        $holiday = SwitchTemporalRule::factory()->for($account)->create([
            'switch_resource_id' => 'switch-rule-holiday',
            'name' => 'Holiday',
        ]);
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-reception',
            'display_name' => 'Reception',
        ]);
        $voicemail = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-mailbox-closed',
            'name' => 'Closed mailbox',
        ]);
        $phoneNumber = SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15550000903',
            'assigned_callflow_id' => null,
        ]);
        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldReceive('create')->once()->withArgs(fn (
            SwitchAccount $received,
            string $name,
            string $module,
            ?string $resourceId,
            array $numbers,
            ?string $fallbackModule,
            ?string $fallbackResourceId,
            array $branches,
            array $ruleIds,
        ): bool => $received->is($account)
            && $name === 'Direct schedule'
            && $module === 'temporal_route'
            && $resourceId === null
            && $numbers === ['+15550000903']
            && $fallbackModule === null
            && $fallbackResourceId === null
            && $ruleIds === ['switch-rule-holiday', 'switch-rule-weekday']
            && $branches === [
                [
                    'key' => 'switch-rule-holiday',
                    'module' => 'voicemail',
                    'resource_id' => 'switch-mailbox-closed',
                ],
                [
                    'key' => 'switch-rule-weekday',
                    'module' => 'user',
                    'resource_id' => 'switch-user-reception',
                ],
            ])
            ->andReturn([
                'id' => 'switch-callflow-direct-temporal',
                'name' => 'Direct schedule',
                'numbers' => ['+15550000903'],
                'flow' => [
                    'module' => 'temporal_route',
                    'data' => ['rules' => ['switch-rule-holiday', 'switch-rule-weekday']],
                    'children' => [
                        'switch-rule-holiday' => [
                            'module' => 'voicemail',
                            'data' => ['id' => 'switch-mailbox-closed'],
                            'children' => [],
                        ],
                        'switch-rule-weekday' => [
                            'module' => 'user',
                            'data' => ['id' => 'switch-user-reception'],
                            'children' => [],
                        ],
                    ],
                ],
            ]);
        $gateway->shouldReceive('updateDestination')->once()->withArgs(fn (
            SwitchAccount $received,
            string $callflowResourceId,
            string $module,
            ?string $resourceId,
            ?string $name,
            array $assignedNumbers,
            array $knownNumbers,
            bool $replaceFallback,
            ?string $fallbackModule,
            ?string $fallbackResourceId,
            array $branches,
            array $ruleIds,
        ): bool => $received->is($account)
            && $callflowResourceId === 'switch-callflow-direct-temporal'
            && $module === 'temporal_route'
            && $resourceId === null
            && $name === 'Direct schedule'
            && $assignedNumbers === ['+15550000903']
            && $knownNumbers === ['+15550000903']
            && ! $replaceFallback
            && $fallbackModule === null
            && $fallbackResourceId === null
            && $ruleIds === ['switch-rule-weekday']
            && $branches === [
                [
                    'key' => 'switch-rule-holiday',
                    'module' => null,
                    'resource_id' => null,
                ],
                [
                    'key' => 'switch-rule-weekday',
                    'module' => 'voicemail',
                    'resource_id' => 'switch-mailbox-closed',
                ],
            ])
            ->andReturn([
                'id' => 'switch-callflow-direct-temporal',
                'name' => 'Direct schedule',
                'numbers' => ['+15550000903'],
                'flow' => [
                    'module' => 'temporal_route',
                    'data' => ['rules' => ['switch-rule-weekday']],
                    'children' => [
                        'switch-rule-weekday' => [
                            'module' => 'voicemail',
                            'data' => ['id' => 'switch-mailbox-closed'],
                            'children' => [],
                        ],
                    ],
                ],
            ]);

        $createdResponse = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflows", [
                'name' => 'Direct schedule',
                'destination_type' => 'temporal_rules',
                'destination_id' => null,
                'temporal_rule_ids' => [$holiday->id, $weekday->id],
                'temporal_rule_routes' => [
                    [
                        'rule_id' => $holiday->id,
                        'destination_type' => 'voicemail',
                        'destination_id' => $voicemail->id,
                    ],
                    [
                        'rule_id' => $weekday->id,
                        'destination_type' => 'extension',
                        'destination_id' => $extension->id,
                    ],
                ],
                'phone_number_ids' => [$phoneNumber->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.flow.temporal_rules.0.id', $holiday->id)
            ->assertJsonPath('data.flow.temporal_rules.1.id', $weekday->id)
            ->assertJsonPath('data.flow.children.preserved_1.target.id', $voicemail->id)
            ->assertJsonPath('data.flow.children.preserved_2.target.id', $extension->id);
        $this->assertStringNotContainsString('switch-rule-holiday', $createdResponse->getContent());
        $this->assertStringNotContainsString('switch-rule-weekday', $createdResponse->getContent());

        $callflow = $account->callflows()
            ->where('switch_resource_id', 'switch-callflow-direct-temporal')
            ->firstOrFail();
        $editorResponse = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/editor")
            ->assertOk()
            ->assertJsonPath('data.direct_temporal_routes.0.rule_id', $holiday->id)
            ->assertJsonPath('data.direct_temporal_routes.0.target.id', $voicemail->id)
            ->assertJsonPath('data.direct_temporal_routes.1.rule_id', $weekday->id)
            ->assertJsonPath('data.direct_temporal_routes.1.target.id', $extension->id);
        $this->assertStringNotContainsString('switch-rule-holiday', $editorResponse->getContent());
        $this->assertStringNotContainsString('switch-rule-weekday', $editorResponse->getContent());

        $updatedResponse = $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}", [
                'name' => 'Direct schedule',
                'destination_type' => 'temporal_rules',
                'destination_id' => null,
                'temporal_rule_ids' => [$weekday->id],
                'temporal_rule_routes' => [[
                    'rule_id' => $weekday->id,
                    'destination_type' => 'voicemail',
                    'destination_id' => $voicemail->id,
                ]],
                'phone_number_ids' => [$phoneNumber->id],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.flow.temporal_rules')
            ->assertJsonPath('data.flow.temporal_rules.0.id', $weekday->id)
            ->assertJsonPath('data.flow.children.preserved_1.target.id', $voicemail->id);
        $this->assertStringNotContainsString('switch-rule-holiday', $updatedResponse->getContent());
        $this->assertStringNotContainsString('switch-rule-weekday', $updatedResponse->getContent());
    }

    public function test_it_clears_the_rule_set_match_branch_and_preserves_legacy_temporal_children(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $set = SwitchTemporalRuleSet::factory()->for($account)->create([
            'switch_resource_id' => 'switch-set-office',
        ]);
        $rule = SwitchTemporalRule::factory()->for($account)->create([
            'switch_resource_id' => 'switch-rule-weekdays',
        ]);
        $set->rules()->create([
            'switch_temporal_rule_id' => $rule->getKey(),
            'switch_rule_resource_id' => $rule->switch_resource_id,
            'position' => 0,
        ]);
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-reception',
            'display_name' => 'Reception',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'switch-callflow-hours',
            'name' => 'Office schedule',
            'flow_structure' => [
                'module' => 'temporal_route',
                'target' => ['type' => 'temporal_rule_set', 'id' => $set->id, 'label' => $set->name],
                'reference_status' => 'resolved',
                'children' => [
                    'rule_set' => [
                        'module' => 'user',
                        'target' => ['type' => 'extension', 'id' => $extension->id, 'label' => 'Reception'],
                        'reference_status' => 'resolved',
                        'children' => [],
                    ],
                    'legacy-rule-resource' => [
                        'module' => 'custom_vendor',
                        'target' => null,
                        'reference_status' => 'not_applicable',
                        'children' => [],
                    ],
                ],
            ],
        ]);

        $gateway = $this->mock(SwitchCallflowGateway::class);
        $gateway->shouldReceive('updateDestination')->once()->withArgs(fn (
            SwitchAccount $received,
            string $resourceId,
            string $module,
            string $targetResourceId,
            ?string $name,
            array $assignedNumbers,
            array $knownNumbers,
            bool $replaceFallback,
            ?string $fallbackModule,
            ?string $fallbackResourceId,
            array $branches,
        ): bool => $received->is($account)
            && $resourceId === 'switch-callflow-hours'
            && $module === 'temporal_route'
            && $targetResourceId === 'switch-set-office'
            && $name === 'Office schedule'
            && $assignedNumbers === []
            && $knownNumbers === []
            && ! $replaceFallback
            && $fallbackModule === null
            && $fallbackResourceId === null
            && $branches === [[
                'key' => 'rule_set',
                'module' => null,
                'resource_id' => null,
            ]])
            ->andReturn([
                'id' => 'switch-callflow-hours',
                'name' => 'Office schedule',
                'numbers' => [],
                'flow' => [
                    'module' => 'temporal_route',
                    'data' => ['rule_set' => 'switch-set-office'],
                    'children' => [
                        'legacy-rule-resource' => [
                            'module' => 'custom_vendor',
                            'data' => ['preserve' => true],
                            'children' => [],
                        ],
                    ],
                ],
            ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}/editor")
            ->assertOk()
            ->assertJsonPath('data.temporal_match.target.id', $extension->id)
            ->assertJsonPath('data.temporal_match.preserved_branch_count', 1)
            ->assertJsonMissingPath('data.temporal_match.branch_key');

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}", [
                'name' => 'Office schedule',
                'destination_type' => 'temporal_rule_set',
                'destination_id' => $set->id,
                'manage_temporal_match' => true,
                'temporal_match_destination_type' => null,
                'temporal_match_destination_id' => null,
                'phone_number_ids' => [],
            ])
            ->assertOk()
            ->assertJsonMissingPath('data.flow.children.rule_set')
            ->assertJsonPath('data.flow.children.preserved_1.module', 'custom_vendor')
            ->assertJsonPath('data.flow.children.preserved_1.branch.kind', 'preserved');
    }

    public function test_it_rejects_a_phone_number_assigned_to_another_route(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create();
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'flow_structure' => [
                'module' => 'user',
                'target' => [
                    'type' => 'extension',
                    'id' => $extension->id,
                    'label' => 'Extension',
                ],
                'reference_status' => 'resolved',
                'children' => [],
            ],
        ]);
        $otherCallflow = SwitchCallflow::factory()->for($account)->create(['name' => 'Other route']);
        $conflictingNumber = SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15550000300',
            'assigned_callflow_id' => $otherCallflow->getKey(),
        ]);
        $this->app->instance(SwitchCallflowGateway::class, new class implements SwitchCallflowGateway
        {
            public function create(
                SwitchAccount $account,
                string $name,
                string $destinationModule,
                ?string $destinationResourceId,
                array $phoneNumbers,
                ?string $fallbackModule = null,
                ?string $fallbackResourceId = null,
                array $menuBranches = [],
                array $destinationTemporalRuleIds = [],
            ): array {
                throw new \LogicException('Not used by this test.');
            }

            public function updateDestination(
                SwitchAccount $account,
                string $resourceId,
                string $destinationModule,
                ?string $destinationResourceId,
                ?string $name,
                array $assignedPhoneNumbers,
                array $knownPhoneNumbers,
                bool $replaceFallback = false,
                ?string $fallbackModule = null,
                ?string $fallbackResourceId = null,
                array $menuBranchOperations = [],
                array $destinationTemporalRuleIds = [],
            ): array {
                throw new \LogicException('Conflicting assignments must be rejected before Switch is called.');
            }

            public function delete(SwitchAccount $account, string $resourceId): void
            {
                throw new \LogicException('Not used by this test.');
            }

            public function moveTreeNode(SwitchAccount $account, string $resourceId, array $sourcePath, array $destinationParentPath, string $destinationBranch): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function writeTreeNode(SwitchAccount $account, string $resourceId, string $operation, array $path, ?string $branch, string $module, string $targetResourceId): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function reorderTreeNodes(SwitchAccount $account, string $resourceId, string $mode, array $sourcePath, array $targetPath): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function writeInlineTreeNode(SwitchAccount $account, string $resourceId, string $operation, array $path, ?string $branch, string $module, array $settings): array
            {
                throw new \LogicException('Not used by this test.');
            }
        });

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}", [
                'name' => 'Blocked assignment',
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
                'phone_number_ids' => [$conflictingNumber->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone_number_ids')
            ->assertJsonPath(
                'errors.phone_number_ids.0',
                'The following phone numbers are already assigned to another route: +15550000300.',
            );
    }

    public function test_it_creates_a_guided_route_and_assigns_its_phone_number(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-create',
            'display_name' => 'Sales',
        ]);
        $phoneNumber = SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15550000400',
            'assigned_callflow_id' => null,
        ]);
        $voicemail = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-mailbox-create',
            'name' => 'Sales fallback',
        ]);
        $gateway = new class implements SwitchCallflowGateway
        {
            /** @var array<string, mixed> */
            public array $received = [];

            public function create(
                SwitchAccount $account,
                string $name,
                string $destinationModule,
                ?string $destinationResourceId,
                array $phoneNumbers,
                ?string $fallbackModule = null,
                ?string $fallbackResourceId = null,
                array $menuBranches = [],
                array $destinationTemporalRuleIds = [],
            ): array {
                $this->received = compact(
                    'name',
                    'destinationModule',
                    'destinationResourceId',
                    'phoneNumbers',
                    'fallbackModule',
                    'fallbackResourceId',
                );

                $children = $fallbackModule !== null && $fallbackResourceId !== null
                    ? [
                        '_' => [
                            'module' => $fallbackModule,
                            'data' => ['id' => $fallbackResourceId],
                            'children' => [],
                        ],
                    ]
                    : [];

                return [
                    'id' => 'switch-callflow-created',
                    'name' => $name,
                    'numbers' => $phoneNumbers,
                    'flow' => [
                        'module' => $destinationModule,
                        'data' => ['id' => $destinationResourceId],
                        'children' => $children,
                    ],
                ];
            }

            public function updateDestination(
                SwitchAccount $account,
                string $resourceId,
                string $destinationModule,
                ?string $destinationResourceId,
                ?string $name,
                array $assignedPhoneNumbers,
                array $knownPhoneNumbers,
                bool $replaceFallback = false,
                ?string $fallbackModule = null,
                ?string $fallbackResourceId = null,
                array $menuBranchOperations = [],
                array $destinationTemporalRuleIds = [],
            ): array {
                throw new \LogicException('Not used by this test.');
            }

            public function delete(SwitchAccount $account, string $resourceId): void
            {
                throw new \LogicException('Not used by this test.');
            }

            public function moveTreeNode(SwitchAccount $account, string $resourceId, array $sourcePath, array $destinationParentPath, string $destinationBranch): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function writeTreeNode(SwitchAccount $account, string $resourceId, string $operation, array $path, ?string $branch, string $module, string $targetResourceId): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function reorderTreeNodes(SwitchAccount $account, string $resourceId, string $mode, array $sourcePath, array $targetPath): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function writeInlineTreeNode(SwitchAccount $account, string $resourceId, string $operation, array $path, ?string $branch, string $module, array $settings): array
            {
                throw new \LogicException('Not used by this test.');
            }
        };
        $this->app->instance(SwitchCallflowGateway::class, $gateway);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/editor")
            ->assertOk()
            ->assertJsonPath('data.mode', 'create')
            ->assertJsonPath('data.fallback.editable', true)
            ->assertJsonPath('data.phone_numbers.0.id', $phoneNumber->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflows", [
                'name' => 'Sales main line',
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
                'manage_fallback' => true,
                'fallback_destination_type' => 'voicemail',
                'fallback_destination_id' => $voicemail->id,
                'phone_number_ids' => [$phoneNumber->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sales main line')
            ->assertJsonPath('data.flow.target.id', $extension->id)
            ->assertJsonPath('data.flow.children._.target.id', $voicemail->id)
            ->assertJsonPath('data.phone_numbers.0.id', $phoneNumber->id);

        $createdId = $response->json('data.id');
        $this->assertIsString($createdId);
        $this->assertDatabaseHas('switch_callflows', [
            'id' => $createdId,
            'switch_resource_id' => 'switch-callflow-created',
        ]);
        $this->assertNotNull($phoneNumber->fresh()->assigned_callflow_id);
        $this->assertSame(['+15550000400'], $gateway->received['phoneNumbers']);
        $this->assertSame('voicemail', $gateway->received['fallbackModule']);
        $this->assertSame('switch-mailbox-create', $gateway->received['fallbackResourceId']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'callflow.created', 'outcome' => 'succeeded']);
    }

    public function test_it_deletes_an_unreferenced_route_and_blocks_a_referenced_route(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $deletable = SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => null,
            'owner_switch_resource_id' => null,
            'numbers' => [],
            'is_feature_code' => false,
        ]);
        $protected = SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => null,
            'owner_switch_resource_id' => null,
            'numbers' => [],
            'is_feature_code' => false,
        ]);
        SwitchCallflow::factory()->for($account)->create([
            'flow_structure' => [
                'module' => 'callflow',
                'target' => ['type' => 'callflow', 'id' => $protected->id, 'label' => 'Protected'],
                'reference_status' => 'resolved',
                'children' => [],
            ],
        ]);
        $gateway = new class implements SwitchCallflowGateway
        {
            public ?string $deletedResourceId = null;

            public function create(SwitchAccount $account, string $name, string $destinationModule, ?string $destinationResourceId, array $phoneNumbers, ?string $fallbackModule = null, ?string $fallbackResourceId = null, array $menuBranches = [], array $destinationTemporalRuleIds = []): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function updateDestination(SwitchAccount $account, string $resourceId, string $destinationModule, ?string $destinationResourceId, ?string $name, array $assignedPhoneNumbers, array $knownPhoneNumbers, bool $replaceFallback = false, ?string $fallbackModule = null, ?string $fallbackResourceId = null, array $menuBranchOperations = [], array $destinationTemporalRuleIds = []): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function delete(SwitchAccount $account, string $resourceId): void
            {
                $this->deletedResourceId = $resourceId;
            }

            public function moveTreeNode(SwitchAccount $account, string $resourceId, array $sourcePath, array $destinationParentPath, string $destinationBranch): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function writeTreeNode(SwitchAccount $account, string $resourceId, string $operation, array $path, ?string $branch, string $module, string $targetResourceId): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function reorderTreeNodes(SwitchAccount $account, string $resourceId, string $mode, array $sourcePath, array $targetPath): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function writeInlineTreeNode(SwitchAccount $account, string $resourceId, string $operation, array $path, ?string $branch, string $module, array $settings): array
            {
                throw new \LogicException('Not used by this test.');
            }
        };
        $this->app->instance(SwitchCallflowGateway::class, $gateway);

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/callflows/{$protected->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('callflow');

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/callflows/{$deletable->id}")
            ->assertNoContent();

        $this->assertSame($deletable->switch_resource_id, $gateway->deletedResourceId);
        $this->assertSoftDeleted($deletable);
        $this->assertDatabaseHas('audit_logs', ['action' => 'callflow.deleted', 'outcome' => 'succeeded']);
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->getKey(), [
            'role' => OrganizationRole::AccountOperator->value,
        ]);
        $account = SwitchAccount::factory()->for($organization)->create();

        return [$user, $account];
    }
}
