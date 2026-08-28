<?php

namespace Tests\Feature\Domains\CallRouting;

use App\Domains\Auditing\Models\AuditLog;
use App\Domains\CallRouting\Contracts\SwitchCallflowGateway;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
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
            ->assertJsonPath('meta.sync.status', 'healthy')
            ->assertJsonMissingPath('data.0.callflow_id')
            ->assertJsonMissingPath('data.0.switch_resource_id')
            ->assertJsonMissingPath('data.0.switch_json')
            ->assertJsonMissingPath('data.0.flow.data');

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
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'switch-callflow-main',
            'name' => 'Main route',
            'numbers' => ['18005550100'],
            'flow_structure' => [
                'module' => 'play',
                'target' => null,
                'reference_status' => 'unresolved',
                'children' => [],
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
                string $destinationResourceId,
                array $phoneNumbers,
            ): array {
                throw new \LogicException('Not used by this test.');
            }

            public function updateDestination(
                SwitchAccount $account,
                string $resourceId,
                string $destinationModule,
                string $destinationResourceId,
                ?string $name,
                array $assignedPhoneNumbers,
                array $knownPhoneNumbers,
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

    public function test_read_only_users_cannot_update_call_routing(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->getKey(), [
            'role' => OrganizationRole::ReadOnlyUser->value,
        ]);
        $account = SwitchAccount::factory()->for($organization)->create();
        $extension = SwitchExtension::factory()->for($account)->create();
        $callflow = SwitchCallflow::factory()->for($account)->create();

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/callflows/{$callflow->id}", [
                'name' => 'Blocked route',
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
                'phone_number_ids' => [],
            ])
            ->assertForbidden();
    }

    public function test_it_rejects_a_phone_number_assigned_to_another_route(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create();
        $callflow = SwitchCallflow::factory()->for($account)->create();
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
                string $destinationResourceId,
                array $phoneNumbers,
            ): array {
                throw new \LogicException('Not used by this test.');
            }

            public function updateDestination(
                SwitchAccount $account,
                string $resourceId,
                string $destinationModule,
                string $destinationResourceId,
                ?string $name,
                array $assignedPhoneNumbers,
                array $knownPhoneNumbers,
            ): array {
                throw new \LogicException('Conflicting assignments must be rejected before Switch is called.');
            }

            public function delete(SwitchAccount $account, string $resourceId): void
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
        $gateway = new class implements SwitchCallflowGateway
        {
            /** @var array<string, mixed> */
            public array $received = [];

            public function create(
                SwitchAccount $account,
                string $name,
                string $destinationModule,
                string $destinationResourceId,
                array $phoneNumbers,
            ): array {
                $this->received = compact('name', 'destinationModule', 'destinationResourceId', 'phoneNumbers');

                return [
                    'id' => 'switch-callflow-created',
                    'name' => $name,
                    'numbers' => $phoneNumbers,
                    'flow' => [
                        'module' => $destinationModule,
                        'data' => ['id' => $destinationResourceId],
                        'children' => [],
                    ],
                ];
            }

            public function updateDestination(
                SwitchAccount $account,
                string $resourceId,
                string $destinationModule,
                string $destinationResourceId,
                ?string $name,
                array $assignedPhoneNumbers,
                array $knownPhoneNumbers,
            ): array {
                throw new \LogicException('Not used by this test.');
            }

            public function delete(SwitchAccount $account, string $resourceId): void
            {
                throw new \LogicException('Not used by this test.');
            }
        };
        $this->app->instance(SwitchCallflowGateway::class, $gateway);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflows/editor")
            ->assertOk()
            ->assertJsonPath('data.mode', 'create')
            ->assertJsonPath('data.phone_numbers.0.id', $phoneNumber->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflows", [
                'name' => 'Sales main line',
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
                'phone_number_ids' => [$phoneNumber->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sales main line')
            ->assertJsonPath('data.flow.target.id', $extension->id)
            ->assertJsonPath('data.phone_numbers.0.id', $phoneNumber->id);

        $createdId = $response->json('data.id');
        $this->assertIsString($createdId);
        $this->assertDatabaseHas('switch_callflows', [
            'id' => $createdId,
            'switch_resource_id' => 'switch-callflow-created',
        ]);
        $this->assertNotNull($phoneNumber->fresh()->assigned_callflow_id);
        $this->assertSame(['+15550000400'], $gateway->received['phoneNumbers']);
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

            public function create(SwitchAccount $account, string $name, string $destinationModule, string $destinationResourceId, array $phoneNumbers): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function updateDestination(SwitchAccount $account, string $resourceId, string $destinationModule, string $destinationResourceId, ?string $name, array $assignedPhoneNumbers, array $knownPhoneNumbers): array
            {
                throw new \LogicException('Not used by this test.');
            }

            public function delete(SwitchAccount $account, string $resourceId): void
            {
                $this->deletedResourceId = $resourceId;
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
