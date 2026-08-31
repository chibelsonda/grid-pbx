<?php

namespace Tests\Feature\Domains\LineKeys;

use App\Domains\Devices\Contracts\SwitchProvisioningCatalogGateway;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\LineKeys\Contracts\SwitchLineKeyGateway;
use App\Domains\LineKeys\Models\SwitchLineKey;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LineKeyControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_lists_device_line_key_projections_without_internal_or_source_fields(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'name' => 'Reception phone',
            'make' => 'Yealink',
            'endpoint_family' => 'T5',
            'model' => 'T54W',
        ]);
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-support',
        ]);
        $extension->delete();
        SwitchLineKey::query()->create([
            'switch_device_id' => $device->getKey(),
            'category' => 'feature',
            'position' => 1,
            'type' => 'presence',
            'label' => 'Support',
            'value' => 'switch-user-support',
            'switch_json' => ['type' => 'speed_dial', 'value' => ['label' => 'Support', 'value' => 'switch-user-support']],
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/line-keys")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Reception phone')
            ->assertJsonPath('data.0.endpoint_family', 'T5')
            ->assertJsonPath('data.0.line_keys.0.label', 'Support')
            ->assertJsonPath('data.0.line_keys.0.value', $extension->id)
            ->assertJsonMissingPath('data.0.device_id')
            ->assertJsonMissingPath('data.0.line_keys.0.line_key_id')
            ->assertJsonMissingPath('data.0.line_keys.0.switch_json')
            ->assertDontSee('switch-user-support');
    }

    public function test_preview_is_safe_and_explains_when_apply_is_disabled(): void
    {
        config()->set('switch.line_key_mutations_enabled', false);
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'make' => 'Yealink',
            'model' => 'T54W',
            'switch_json' => ['sip' => ['username' => 'phone-user', 'password' => '[REDACTED]']],
        ]);
        SwitchLineKey::query()->create([
            'switch_device_id' => $device->getKey(),
            'category' => 'combo',
            'position' => 0,
            'type' => 'line',
            'value' => '1001',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys/preview")
            ->assertOk()
            ->assertJsonPath('data.capability.preview_available', true)
            ->assertJsonPath('data.capability.apply_available', false)
            ->assertJsonPath('data.payload_preview.provision.combo_keys.0.type', 'line')
            ->assertJsonPath('data.device.line_keys.0.value', null)
            ->assertJsonPath('data.device.line_keys.0.label', null)
            ->assertJsonMissingPath('data.payload_preview.provision.combo_keys.0.value')
            ->assertJsonMissingPath('data.device.switch_json')
            ->assertDontSee('phone-user');
    }

    public function test_disabled_mutations_do_not_call_switch(): void
    {
        config()->set('switch.line_key_mutations_enabled', false);
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create(['make' => 'Yealink', 'model' => 'T54W']);
        $this->mock(SwitchLineKeyGateway::class)->shouldNotReceive('update');

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys", ['line_keys' => []])
            ->assertConflict()
            ->assertJsonPath('message', 'Line-key mutations are disabled by server configuration.');
    }

    public function test_preview_requires_a_mac_address_for_line_key_apply(): void
    {
        config()->set('switch.line_key_mutations_enabled', true);
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-reception',
            'make' => 'Yealink',
            'model' => 'T54W',
            'mac_address' => null,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys/preview")
            ->assertOk()
            ->assertJsonPath('data.capability.apply_available', false)
            ->assertJsonPath(
                'data.capability.reason',
                'The device needs an endpoint brand, model, and MAC address before it can be provisioned.',
            );
    }

    public function test_preview_returns_selected_model_line_key_capabilities(): void
    {
        config()->set('switch.line_key_mutations_enabled', true);
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-reception',
            'make' => 'yealink',
            'endpoint_family' => 't5',
            'model' => 't54w',
            'mac_address' => '00:11:22:33:44:55',
        ]);
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1001',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        SwitchLineKey::query()->create([
            'switch_device_id' => $device->getKey(),
            'category' => 'feature',
            'position' => 1,
            'type' => 'presence',
            'value' => 'switch-user-1001',
        ]);
        SwitchExtension::factory()->create([
            'switch_resource_id' => 'foreign-switch-user',
            'display_name' => 'Foreign Operator',
        ]);
        $this->mock(SwitchProvisioningCatalogGateway::class)
            ->shouldReceive('catalog')
            ->once()
            ->andReturn($this->provisioningCatalog());

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys/preview")
            ->assertOk()
            ->assertJsonPath('data.capability.model.matched', true)
            ->assertJsonPath('data.capability.model.max_keys', 10)
            ->assertJsonPath('data.capability.model.max_expansion_modules', 3)
            ->assertJsonPath('data.capability.model.keys_per_expansion_module', 20)
            ->assertJsonPath('data.capability.model.total_keys', 70)
            ->assertJsonPath('data.capability.model.supported_key_types.1', 'presence')
            ->assertJsonCount(2, 'data.value_choices')
            ->assertJsonPath('data.value_choices.0.id', $extension->id)
            ->assertJsonPath('data.value_choices.0.source', 'extensions')
            ->assertJsonPath('data.value_choices.0.types.0', 'presence')
            ->assertJsonPath('data.value_choices.0.types.1', 'personal_parking')
            ->assertJsonPath('data.value_choices.0.value', $extension->id)
            ->assertJsonPath('data.value_choices.0.label', 'Alice Operator')
            ->assertJsonPath('data.value_choices.0.description', '1001')
            ->assertJsonPath('data.value_choices.1.id', $extension->id)
            ->assertJsonPath('data.value_choices.1.source', 'extensions')
            ->assertJsonPath('data.value_choices.1.types.0', 'speed_dial')
            ->assertJsonPath('data.value_choices.1.value', '1001')
            ->assertJsonPath('data.device.line_keys.0.value', $extension->id)
            ->assertJsonPath('data.payload_preview.provision.feature_keys.1.value', $extension->id)
            ->assertDontSee('switch-user-1001')
            ->assertDontSee('foreign-switch-user');
    }

    public function test_enabled_mutation_patches_switch_and_reprojects_redacted_device_data(): void
    {
        config()->set('switch.line_key_mutations_enabled', true);
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-1',
            'make' => 'Yealink',
            'model' => 'T54W',
        ]);
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-support',
        ]);
        $this->mock(SwitchLineKeyGateway::class)
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, string $resourceId, array $keys): bool => $receivedAccount->is($account)
                && $resourceId === 'switch-device-1'
                && $keys[0]['position'] === 2
                && $keys[0]['value'] === 'switch-user-support')
            ->andReturn([
                'id' => 'switch-device-1',
                'name' => 'Reception phone',
                'provision' => [
                    'endpoint_brand' => 'Yealink',
                    'endpoint_family' => 'T5',
                    'endpoint_model' => 'T54W',
                    'combo_keys' => [],
                    'feature_keys' => ['2' => ['type' => 'presence', 'value' => ['label' => 'Support', 'value' => 'switch-user-support']]],
                ],
                'sip' => ['password' => 'secret-from-switch'],
            ]);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys", [
                'line_keys' => [[
                    'category' => 'feature',
                    'position' => 2,
                    'type' => 'presence',
                    'value' => $extension->id,
                    'label' => 'Support',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.device.endpoint_family', 'T5')
            ->assertJsonPath('data.device.line_keys.0.label', 'Support')
            ->assertJsonPath('data.device.line_keys.0.value', $extension->id)
            ->assertDontSee('switch-user-support')
            ->assertDontSee('secret-from-switch');

        $device->refresh();
        $this->assertSame('[REDACTED]', $device->switch_json['sip']['password']);
        $this->assertDatabaseHas('switch_line_keys', ['value' => 'switch-user-support']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'line_keys.updated', 'outcome' => 'succeeded']);
    }

    public function test_invalid_key_shapes_are_rejected_before_switch_mutation(): void
    {
        config()->set('switch.line_key_mutations_enabled', true);
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'make' => 'Yealink',
            'model' => 'T54W',
        ]);
        $this->mock(SwitchLineKeyGateway::class)->shouldNotReceive('update');

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys", [
                'line_keys' => [
                    [
                        'category' => 'combo',
                        'position' => 0,
                        'type' => 'speed_dial',
                        'value' => 1001,
                        'label' => null,
                    ],
                    [
                        'category' => 'combo',
                        'position' => 0,
                        'type' => 'parking',
                        'value' => 11,
                        'label' => null,
                    ],
                    [
                        'category' => 'feature',
                        'position' => 1,
                        'type' => 'presence',
                        'value' => null,
                        'label' => 'Reception',
                    ],
                    [
                        'category' => 'feature',
                        'position' => 2,
                        'type' => 'parking',
                        'value' => '1.5',
                        'label' => null,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'line_keys.0.value',
                'line_keys.1.position',
                'line_keys.1.value',
                'line_keys.2.value',
                'line_keys.3.value',
            ]);
    }

    public function test_it_rejects_foreign_public_references_and_unknown_fields_before_switch_mutation(): void
    {
        config()->set('switch.line_key_mutations_enabled', true);
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'make' => 'Yealink',
            'model' => 'T54W',
        ]);
        $foreignExtension = SwitchExtension::factory()->create();
        $this->mock(SwitchLineKeyGateway::class)->shouldNotReceive('update');

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys", [
                'line_keys' => [[
                    'category' => 'feature',
                    'position' => 0,
                    'type' => 'presence',
                    'value' => $foreignExtension->id,
                    'label' => null,
                    'switch_json' => ['vendor' => 'operator-controlled'],
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['line_keys.0']);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys", [
                'line_keys' => [[
                    'category' => 'feature',
                    'position' => 0,
                    'type' => 'presence',
                    'value' => $device->id,
                    'label' => null,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['line_keys.0.value']);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys", [
                'line_keys' => [[
                    'category' => 'feature',
                    'position' => 0,
                    'type' => 'presence',
                    'value' => $foreignExtension->id,
                    'label' => null,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['line_keys.0.value']);
    }

    public function test_model_capability_violations_return_422_before_switch_mutation(): void
    {
        config()->set('switch.line_key_mutations_enabled', true);
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-reception',
            'make' => 'yealink',
            'endpoint_family' => 't5',
            'model' => 't54w',
            'mac_address' => '00:11:22:33:44:55',
        ]);
        $this->mock(SwitchProvisioningCatalogGateway::class)
            ->shouldReceive('catalog')
            ->once()
            ->andReturn($this->provisioningCatalog());
        $this->mock(SwitchLineKeyGateway::class)->shouldNotReceive('update');

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys", [
                'line_keys' => [
                    [
                        'category' => 'combo',
                        'position' => 70,
                        'type' => 'parking',
                        'value' => 1,
                        'label' => null,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['line_keys.0.position', 'line_keys.0.type']);
    }

    public function test_speed_dial_uses_the_dialable_extension_instead_of_a_switch_resource_id(): void
    {
        config()->set('switch.line_key_mutations_enabled', true);
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-1',
            'make' => 'Yealink',
            'model' => 'T54W',
        ]);
        $this->mock(SwitchLineKeyGateway::class)
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, string $resourceId, array $keys): bool => $receivedAccount->is($account)
                && $resourceId === 'switch-device-1'
                && $keys[0]['value'] === '1001')
            ->andReturn([
                'id' => 'switch-device-1',
                'provision' => [
                    'endpoint_brand' => 'Yealink',
                    'endpoint_model' => 'T54W',
                    'combo_keys' => [],
                    'feature_keys' => ['0' => ['type' => 'speed_dial', 'value' => '1001']],
                ],
            ]);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys", [
                'line_keys' => [[
                    'category' => 'feature',
                    'position' => 0,
                    'type' => 'speed_dial',
                    'value' => '1001',
                    'label' => null,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.device.line_keys.0.value', '1001');
    }

    public function test_line_appearances_require_combo_keys_and_no_value_or_label(): void
    {
        config()->set('switch.line_key_mutations_enabled', true);
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'make' => 'Yealink',
            'model' => 'T54W',
        ]);
        $this->mock(SwitchLineKeyGateway::class)->shouldNotReceive('update');

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/line-keys", [
                'line_keys' => [[
                    'category' => 'feature',
                    'position' => 0,
                    'type' => 'line',
                    'value' => '1001',
                    'label' => 'Line 1',
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'line_keys.0.category',
                'line_keys.0.value',
                'line_keys.0.label',
            ]);
    }

    /** @return array<string, mixed> */
    private function provisioningCatalog(): array
    {
        return [
            'available' => true,
            'reason' => null,
            'brands' => [[
                'id' => 'yealink',
                'name' => 'Yealink',
                'families' => [[
                    'id' => 't5',
                    'name' => 'T5',
                    'models' => [[
                        'id' => 't54w',
                        'name' => 'T54W',
                        'template_id' => 'yealink_t5_t54w',
                        'max_keys' => 10,
                        'max_expansion_modules' => 3,
                        'keys_per_expansion_module' => 20,
                        'supported_key_types' => ['line', 'presence'],
                        'value_sources' => ['extensions', 'devices'],
                        'manufacturer_provider' => 'yealink-rps',
                    ]],
                ]],
            ]],
        ];
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => OrganizationRole::AccountOperator->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
