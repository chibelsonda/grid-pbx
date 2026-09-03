<?php

namespace Tests\Feature\Domains\Devices;

use App\Domains\Auditing\Models\AuditLog;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Contracts\SwitchProvisioningCatalogGateway;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Devices\Services\DeviceMetaflowPolicy;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DeviceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_requires_authentication_to_list_devices(): void
    {
        $account = SwitchAccount::factory()->create();

        $this->getJson("/api/v1/accounts/{$account->id}/devices")
            ->assertUnauthorized();
    }

    public function test_it_lists_and_searches_devices_by_assigned_extension(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        SwitchDevice::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'name' => 'Reception Desk Phone',
            'make' => 'Yealink',
            'model' => 'T54W',
        ]);
        SwitchDevice::factory()->for($account)->create([
            'name' => 'Warehouse Phone',
        ]);
        SyncCheckpoint::query()->create([
            'switch_account_id' => $account->getKey(),
            'resource_type' => 'extensions',
            'status' => ProjectionStatus::Healthy,
            'last_successful_at' => '2026-08-28 06:30:00',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices?search=1001")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Reception Desk Phone')
            ->assertJsonPath('data.0.assigned_extension.display_name', 'Alice Operator')
            ->assertJsonPath('data.0.assigned_extension.extension', '1001')
            ->assertJsonPath('meta.sync.status', 'healthy')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_it_hides_accounts_outside_the_users_organization(): void
    {
        $user = User::factory()->create();
        $account = SwitchAccount::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices")
            ->assertNotFound();
    }

    public function test_it_returns_device_details_without_the_switch_json(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'name' => 'Alice Desk Phone',
            'device_type' => 'sip_device',
            'make' => 'Yealink',
            'model' => 'T54W',
            'mac_address' => '00:11:22:33:44:55',
            'switch_json' => [
                'sip' => ['password' => '[REDACTED]'],
                'provision' => ['endpoint_model' => 42],
            ],
            'registration_status' => 'registered',
            'registration_checked_at' => '2026-08-28 07:00:00',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/{$device->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Alice Desk Phone')
            ->assertJsonPath('data.device_type', 'sip_device')
            ->assertJsonPath('data.registration_status', 'registered')
            ->assertJsonPath('data.registration_checked_at', '2026-08-28T07:00:00+00:00')
            ->assertJsonPath('data.assigned_extension.id', $extension->id)
            ->assertJsonPath('data.configuration.provision.endpoint_model', 42)
            ->assertJsonMissingPath('data.switch_json');
    }

    public function test_it_returns_404_when_the_device_belongs_to_another_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $otherDevice = SwitchDevice::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/{$otherDevice->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested resource was not found.')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace')
            ->assertDontSee(SwitchDevice::class);
    }

    public function test_it_returns_safe_device_form_options_with_dynamic_restrictions(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        SwitchExtension::factory()->create([
            'display_name' => 'Different account',
        ]);
        $media = SwitchMedia::factory()->for($account)->create(['name' => 'Office music']);
        $emergencyNumber = SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15551234567',
            'features' => ['local', 'e911'],
            'cnam_display_name' => 'Main line',
        ]);
        SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15557654321',
            'features' => ['local'],
        ]);
        $gateway = $this->mock(SwitchDeviceGateway::class);
        $gateway->shouldReceive('schemaCompatibility')
            ->once()
            ->andReturn($this->currentDeviceSchemaCompatibility());
        $gateway->shouldReceive('restrictionClassifiers')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount): bool => $receivedAccount->is($account))
            ->andReturn([
                ['key' => 'tollfree_us', 'label' => 'US TollFree', 'emergency' => false],
                ['key' => 'emergency', 'label' => 'Emergency Dispatcher', 'emergency' => true],
            ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/options");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.extensions')
            ->assertJsonPath('data.extensions.0.id', $extension->id)
            ->assertJsonPath('data.extensions.0.display_name', 'Alice Operator')
            ->assertJsonPath('data.media.0.id', $media->id)
            ->assertJsonPath('data.media.0.name', 'Office music')
            ->assertJsonPath('data.caller_id_numbers.0.id', $emergencyNumber->id)
            ->assertJsonPath('data.caller_id_numbers.0.number', '+15551234567')
            ->assertJsonPath('data.caller_id_numbers.0.display_name', 'Main line')
            ->assertJsonPath('data.caller_id_numbers.0.e911_enabled', true)
            ->assertJsonPath('data.caller_id_numbers.1.e911_enabled', false)
            ->assertJsonPath('data.provisioning_catalog.available', false)
            ->assertJsonPath('data.device_schema.source', 'connected_switch')
            ->assertJsonPath('data.device_schema.call_forward.number_max_length', 35)
            ->assertJsonPath('data.device_schema.sip.invite_formats.5', 'strip_plus')
            ->assertJsonPath('data.device_schema.sip.proxy', true)
            ->assertJsonCount(0, 'data.provisioning_catalog.brands')
            ->assertJsonPath('data.restrictions.0.key', 'tollfree_us')
            ->assertJsonPath('data.restrictions.1.emergency', true)
            ->assertJsonMissingPath('data.restrictions.0.regex')
            ->assertDontSee('switch-user-1');
    }

    public function test_device_options_use_a_conservative_legacy_fallback_when_schema_discovery_fails(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchDeviceGateway::class);
        $gateway->shouldReceive('schemaCompatibility')
            ->once()
            ->andThrow(new SwitchRequestException('Schema endpoint unavailable.', 503));
        $gateway->shouldReceive('restrictionClassifiers')
            ->once()
            ->andReturn([]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/options")
            ->assertOk()
            ->assertJsonPath('data.device_schema.source', 'bundled_legacy_fallback')
            ->assertJsonPath('data.device_schema.call_forward.number_max_length', 15)
            ->assertJsonPath('data.device_schema.sip.proxy', false)
            ->assertJsonPath('data.device_schema.provision.template_id', false)
            ->assertJsonPath('data.device_schema.provision.check_sync_event', true);
    }

    public function test_it_returns_discovered_provisioning_templates_from_the_configured_catalog(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchDeviceGateway::class);
        $gateway->shouldReceive('schemaCompatibility')
            ->once()
            ->andReturn($this->currentDeviceSchemaCompatibility());
        $gateway->shouldReceive('restrictionClassifiers')
            ->once()
            ->andReturn([]);
        $this->mock(SwitchProvisioningCatalogGateway::class)
            ->shouldReceive('catalog')
            ->once()
            ->andReturn([
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
                            'supported_key_types' => ['line', 'presence', 'speed_dial'],
                            'value_sources' => ['extensions'],
                            'manufacturer_provider' => 'yealink-rps',
                        ]],
                    ]],
                ]],
            ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/options")
            ->assertOk()
            ->assertJsonPath('data.provisioning_catalog.available', true)
            ->assertJsonPath('data.provisioning_catalog.brands.0.id', 'yealink')
            ->assertJsonPath('data.provisioning_catalog.brands.0.families.0.id', 't5')
            ->assertJsonPath('data.provisioning_catalog.brands.0.families.0.models.0.id', 't54w')
            ->assertJsonPath(
                'data.provisioning_catalog.brands.0.families.0.models.0.template_id',
                'yealink_t5_t54w',
            )
            ->assertJsonPath('data.provisioning_catalog.brands.0.families.0.models.0.max_keys', 10)
            ->assertJsonPath(
                'data.provisioning_catalog.brands.0.families.0.models.0.max_expansion_modules',
                3,
            )
            ->assertJsonPath(
                'data.provisioning_catalog.brands.0.families.0.models.0.supported_key_types.1',
                'presence',
            )
            ->assertJsonPath(
                'data.provisioning_catalog.brands.0.families.0.models.0.manufacturer_provider',
                'yealink-rps',
            );
    }

    public function test_current_device_schema_fields_are_validated_and_forwarded_to_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchDeviceGateway::class);
        $gateway->shouldReceive('schemaCompatibility')
            ->once()
            ->andReturn($this->currentDeviceSchemaCompatibility());
        $gateway->shouldReceive('create')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, array $payload): bool => $receivedAccount->is($account)
                && $payload['call_forward']['number'] === '+1555123456789012345'
                && $payload['sip']['invite_format'] === 'strip_plus'
                && $payload['sip']['custom_sip_interface'] === 'internal'
                && $payload['sip']['forward'] === '192.0.2.10'
                && $payload['sip']['proxy'] === 'sip-proxy.example.test'
                && $payload['sip']['static_invite'] === 'reception'
                && $payload['sip']['transport'] === 'tcp'
                && $payload['provision']['id'] === 'template-t54w'
                && $payload['make'] === 'yealink'
                && $payload['family'] === 't5'
                && $payload['model'] === ['t54w', 't54w-v2'])
            ->andReturn([
                'id' => 'switch-device-current-schema',
                'name' => 'Current schema phone',
                'device_type' => 'sip_device',
                'enabled' => true,
                'call_forward' => ['number' => '+1555123456789012345'],
                'sip' => [
                    'invite_format' => 'strip_plus',
                    'custom_sip_interface' => 'internal',
                    'forward' => '192.0.2.10',
                    'proxy' => 'sip-proxy.example.test',
                    'static_invite' => 'reception',
                    'transport' => 'tcp',
                ],
                'provision' => [
                    'id' => 'template-t54w',
                    'endpoint_brand' => 'yealink',
                    'endpoint_family' => 't5',
                    'endpoint_model' => ['t54w', 't54w-v2'],
                ],
            ]);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Current schema phone',
                'device_type' => 'sip_device',
                'is_enabled' => true,
                'call_forward' => ['number' => '+1555123456789012345'],
                'sip' => [
                    'invite_format' => 'strip_plus',
                    'custom_sip_interface' => 'internal',
                    'forward' => '192.0.2.10',
                    'proxy' => 'sip-proxy.example.test',
                    'static_invite' => 'reception',
                    'transport' => 'tcp',
                ],
                'provision' => [
                    'id' => 'template-t54w',
                    'endpoint_brand' => 'yealink',
                    'endpoint_family' => 't5',
                    'endpoint_model' => ['t54w', 't54w-v2'],
                ],
                'mac_address' => '00:11:22:33:44:77',
            ])
            ->assertCreated()
            ->assertJsonPath('data.configuration.sip.transport', 'tcp')
            ->assertJsonPath('data.configuration.provision.id', 'template-t54w')
            ->assertJsonPath('data.configuration.provision.endpoint_model.1', 't54w-v2');
    }

    public function test_legacy_device_schema_rejects_new_fields_and_uses_legacy_limits(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchDeviceGateway::class);
        $gateway->shouldReceive('schemaCompatibility')
            ->once()
            ->andReturn($this->legacyDeviceSchemaCompatibility());
        $gateway->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Legacy phone',
                'device_type' => 'sip_device',
                'is_enabled' => true,
                'call_forward' => ['number' => '+155512345678901'],
                'sip' => ['invite_format' => 'strip_plus', 'proxy' => 'proxy.example.test'],
                'provision' => ['id' => 'unsupported-template'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'call_forward.number',
                'sip',
                'sip.invite_format',
                'provision',
            ]);
    }

    public function test_sip_uri_accepts_only_its_minimal_configuration_and_contact_list_option(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchDeviceGateway::class);
        $gateway->shouldReceive('schemaCompatibility')
            ->once()
            ->andReturn($this->legacyDeviceSchemaCompatibility());
        $gateway->shouldReceive('create')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, array $payload): bool => $receivedAccount->is($account)
                && $payload['sip'] === [
                    'invite_format' => 'route',
                    'route' => 'sip:support@example.com',
                ]
                && $payload['contact_list'] === ['exclude' => true]
                && ! array_key_exists('call_waiting', $payload)
                && ! array_key_exists('music_on_hold', $payload)
                && ! array_key_exists('formatters', $payload))
            ->andReturn([
                'id' => 'switch-device-sip-uri',
                'name' => 'Support SIP URI',
                'device_type' => 'sip_uri',
                'enabled' => true,
                'sip' => [
                    'invite_format' => 'route',
                    'route' => 'sip:support@example.com',
                ],
                'contact_list' => ['exclude' => true],
            ]);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Support SIP URI',
                'device_type' => 'sip_uri',
                'is_enabled' => true,
                'assigned_extension_id' => null,
                'sip' => [
                    'invite_format' => 'route',
                    'route' => 'sip:support@example.com',
                ],
                'contact_list' => ['exclude' => true],
            ])
            ->assertCreated()
            ->assertJsonPath('data.configuration.sip.route', 'sip:support@example.com')
            ->assertJsonPath('data.configuration.contact_list.exclude', true);
    }

    public function test_sip_uri_rejects_fields_outside_its_device_workflow(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchDeviceGateway::class);
        $gateway->shouldReceive('schemaCompatibility')
            ->once()
            ->andReturn($this->legacyDeviceSchemaCompatibility());
        $gateway->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Invalid SIP URI',
                'device_type' => 'sip_uri',
                'is_enabled' => true,
                'sip' => [
                    'method' => 'password',
                    'invite_format' => 'route',
                    'route' => 'sip:support@example.com',
                ],
                'call_waiting' => ['enabled' => true],
                'music_on_hold' => ['media_id' => null],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sip', 'call_waiting', 'music_on_hold']);
    }

    public function test_cellphone_and_landline_accept_only_forwarding_configuration(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchDeviceGateway::class);
        $gateway->shouldReceive('schemaCompatibility')
            ->twice()
            ->andReturn($this->legacyDeviceSchemaCompatibility());
        $gateway->shouldReceive('create')
            ->twice()
            ->withArgs(fn (SwitchAccount $receivedAccount, array $payload): bool => $receivedAccount->is($account)
                && in_array($payload['device_type'], ['cellphone', 'landline'], true)
                && $payload['is_enabled'] === true
                && $payload['call_forward'] === [
                    'enabled' => true,
                    'number' => '+15551234567',
                    'direct_calls_only' => false,
                    'failover' => false,
                    'ignore_early_media' => true,
                    'keep_caller_id' => true,
                    'require_keypress' => true,
                    'substitute' => true,
                ]
                && $payload['contact_list'] === ['exclude' => true]
                && ! array_key_exists('sip', $payload)
                && ! array_key_exists('media', $payload)
                && ! array_key_exists('caller_id', $payload))
            ->andReturnUsing(static fn (SwitchAccount $account, array $payload): array => [
                'id' => "switch-device-{$payload['device_type']}",
                'name' => $payload['name'],
                'device_type' => $payload['device_type'],
                'enabled' => true,
                'call_forward' => $payload['call_forward'],
                'contact_list' => $payload['contact_list'],
            ]);

        foreach (['cellphone', 'landline'] as $deviceType) {
            $this->actingAs($user)
                ->postJson("/api/v1/accounts/{$account->id}/devices", [
                    'name' => "Test {$deviceType}",
                    'device_type' => $deviceType,
                    'is_enabled' => true,
                    'assigned_extension_id' => null,
                    'call_forward' => [
                        'enabled' => true,
                        'number' => '+15551234567',
                        'direct_calls_only' => false,
                        'failover' => false,
                        'ignore_early_media' => true,
                        'keep_caller_id' => true,
                        'require_keypress' => true,
                        'substitute' => true,
                    ],
                    'contact_list' => ['exclude' => true],
                ])
                ->assertCreated()
                ->assertJsonPath('data.device_type', $deviceType)
                ->assertJsonPath('data.configuration.call_forward.number', '+15551234567')
                ->assertJsonPath('data.configuration.contact_list.exclude', true);
        }
    }

    public function test_forwarding_only_devices_reject_endpoint_fields_and_divergent_enabled_state(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchDeviceGateway::class);
        $gateway->shouldReceive('schemaCompatibility')
            ->once()
            ->andReturn($this->legacyDeviceSchemaCompatibility());
        $gateway->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Invalid landline',
                'device_type' => 'landline',
                'is_enabled' => false,
                'call_forward' => [
                    'enabled' => true,
                    'number' => '',
                ],
                'sip' => ['invite_format' => 'contact'],
                'media' => ['fax_option' => false],
                'flags' => ['must-not-pass'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'call_forward.enabled',
                'call_forward.number',
                'sip',
                'media',
                'flags',
            ]);
    }

    public function test_external_and_emergency_caller_id_must_use_eligible_account_numbers(): void
    {
        [$user, $account] = $this->accessibleAccount();
        SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15551234567',
            'features' => ['local'],
        ]);
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'is_enabled' => true,
                'caller_id' => [
                    'external' => ['number' => '+15550000000'],
                    'emergency' => ['number' => '+15551234567'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'caller_id.external.number',
                'caller_id.emergency.number',
            ]);
    }

    public function test_eligible_projected_numbers_can_be_used_for_caller_id(): void
    {
        [$user, $account] = $this->accessibleAccount();
        SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15551234567',
            'features' => ['local', 'e911'],
        ]);
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, array $payload): bool => $receivedAccount->is($account)
                && $payload['caller_id']['external']['number'] === '+15551234567'
                && $payload['caller_id']['emergency']['number'] === '+15551234567')
            ->andReturn([
                'id' => 'switch-device-caller-id',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'caller_id' => [
                    'external' => ['number' => '+15551234567'],
                    'emergency' => ['number' => '+15551234567'],
                ],
            ]);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'is_enabled' => true,
                'caller_id' => [
                    'external' => ['number' => '+15551234567'],
                    'emergency' => ['number' => '+15551234567'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.configuration.caller_id.emergency.number', '+15551234567');
    }

    public function test_it_rejects_an_oversized_page(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices?per_page=101")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_unauthenticated_request_cannot_create_a_device(): void
    {
        $account = SwitchAccount::factory()->create();

        $this->postJson("/api/v1/accounts/{$account->id}/devices", [])
            ->assertUnauthorized();
    }

    public function test_valid_payload_creates_the_upstream_device_and_redacted_projection(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, array $device): bool => $receivedAccount->is($account)
                && $device['owner_switch_resource_id'] === 'switch-user-1'
                && $device['sip_password'] === 'a-long-random-secret')
            ->andReturn([
                'id' => 'switch-device-1',
                'owner_id' => 'switch-user-1',
                'name' => 'Reception Desk Phone',
                'device_type' => 'sip_device',
                'enabled' => true,
                'mac_address' => '00:11:22:33:44:55',
                'provision' => [
                    'endpoint_brand' => 'Yealink',
                    'endpoint_model' => 'T54W',
                ],
                'sip' => [
                    'username' => 'reception',
                    'password' => 'a-long-random-secret',
                ],
            ]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/accounts/{$account->id}/devices",
            [
                'name' => 'Reception Desk Phone',
                'device_type' => 'sip_device',
                'make' => 'Yealink',
                'model' => 'T54W',
                'mac_address' => '00:11:22:33:44:55',
                'is_enabled' => true,
                'assigned_extension_id' => $extension->id,
                'sip_username' => 'reception',
                'sip_password' => 'a-long-random-secret',
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Reception Desk Phone')
            ->assertJsonPath('data.assigned_extension.id', $extension->id)
            ->assertJsonMissingPath('data.switch_json')
            ->assertDontSee('a-long-random-secret');
        $device = SwitchDevice::query()->where('switch_resource_id', 'switch-device-1')->firstOrFail();
        $this->assertSame($extension->getKey(), $device->switch_extension_id);
        $this->assertSame('[REDACTED]', $device->switch_json['sip']['password']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->getKey(),
            'switch_account_id' => $account->getKey(),
            'action' => 'device.created',
            'resource_id' => 'switch-device-1',
            'outcome' => 'succeeded',
        ]);
        $audit = AuditLog::query()->where('action', 'device.created')->firstOrFail();
        $this->assertStringNotContainsString(
            'a-long-random-secret',
            json_encode($audit->metadata, JSON_THROW_ON_ERROR),
        );
    }

    public function test_update_can_unassign_a_device_without_returning_or_storing_the_new_secret(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'switch_resource_id' => 'switch-device-1',
        ]);
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, string $resourceId, array $payload): bool => $receivedAccount->is($account)
                && $resourceId === 'switch-device-1'
                && $payload['owner_switch_resource_id'] === null
                && $payload['sip_password'] === 'replacement-secret')
            ->andReturn([
                'id' => 'switch-device-1',
                'name' => 'Shared Phone',
                'device_type' => 'sip_device',
                'enabled' => false,
                'sip' => ['password' => 'replacement-secret'],
            ]);

        $response = $this->actingAs($user)->putJson(
            "/api/v1/accounts/{$account->id}/devices/{$device->id}",
            [
                'name' => 'Shared Phone',
                'device_type' => 'sip_device',
                'make' => null,
                'model' => null,
                'mac_address' => null,
                'is_enabled' => false,
                'assigned_extension_id' => null,
                'sip_username' => null,
                'sip_password' => 'replacement-secret',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Shared Phone')
            ->assertJsonPath('data.assigned_extension', null)
            ->assertDontSee('replacement-secret');
        $device->refresh();
        $this->assertNull($device->switch_extension_id);
        $this->assertFalse($device->is_enabled);
        $this->assertSame('[REDACTED]', $device->switch_json['sip']['password']);
    }

    public function test_valid_nested_configuration_is_projected_and_returned_without_sip_credentials(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, array $payload): bool => $receivedAccount->is($account)
                && $payload['device_type'] === 'smartphone'
                && $payload['call_forward']['number'] === '+15551234567'
                && $payload['media']['audio']['codecs'] === ['OPUS', 'PCMU']
                && $payload['media']['bypass_media'] === false
                && $payload['caller_id']['internal']['number'] === '1001'
                && $payload['call_recording']['inbound']['offnet']['time_limit'] === 3600)
            ->andReturn([
                'id' => 'switch-device-advanced',
                'name' => 'Mobile Operator',
                'device_type' => 'smartphone',
                'enabled' => true,
                'call_forward' => [
                    'enabled' => true,
                    'number' => '+15551234567',
                    'require_keypress' => true,
                ],
                'sip' => [
                    'username' => 'mobile-operator',
                    'password' => 'a-long-random-secret',
                    'method' => 'password',
                ],
                'media' => [
                    'audio' => ['codecs' => ['OPUS', 'PCMU']],
                    'bypass_media' => false,
                ],
                'caller_id' => ['internal' => ['name' => 'Mobile Operator', 'number' => '1001']],
                'call_recording' => [
                    'inbound' => [
                        'offnet' => [
                            'enabled' => true,
                            'format' => 'mp3',
                            'record_min_sec' => 5,
                            'record_on_answer' => true,
                            'time_limit' => 3600,
                        ],
                    ],
                ],
                'timezone' => 'America/Los_Angeles',
            ]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/accounts/{$account->id}/devices",
            [
                'name' => 'Mobile Operator',
                'device_type' => 'smartphone',
                'is_enabled' => true,
                'call_forward' => [
                    'enabled' => true,
                    'number' => '+15551234567',
                    'require_keypress' => true,
                ],
                'sip' => [
                    'method' => 'password',
                    'username' => 'mobile-operator',
                    'password' => 'a-long-random-secret',
                ],
                'media' => [
                    'audio' => ['codecs' => ['OPUS', 'PCMU']],
                    'bypass_media' => false,
                ],
                'caller_id' => ['internal' => ['name' => 'Mobile Operator', 'number' => '1001']],
                'call_recording' => [
                    'inbound' => [
                        'offnet' => [
                            'enabled' => true,
                            'format' => 'mp3',
                            'record_min_sec' => 5,
                            'record_on_answer' => true,
                            'time_limit' => 3600,
                        ],
                    ],
                ],
                'timezone' => 'America/Los_Angeles',
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.device_type', 'smartphone')
            ->assertJsonPath('data.configuration.call_forward.number', '+15551234567')
            ->assertJsonPath('data.configuration.media.audio.codecs.0', 'OPUS')
            ->assertJsonPath('data.configuration.caller_id.internal.number', '1001')
            ->assertJsonPath('data.configuration.call_recording.inbound.offnet.time_limit', 3600)
            ->assertJsonPath('data.configuration.sip.username_configured', true)
            ->assertJsonMissingPath('data.configuration.sip.username')
            ->assertJsonMissingPath('data.configuration.sip.password')
            ->assertDontSee('a-long-random-secret');

        $device = SwitchDevice::query()->where('switch_resource_id', 'switch-device-advanced')->firstOrFail();
        $this->assertSame('[REDACTED]', $device->switch_json['sip']['password']);
        $this->assertSame(['OPUS', 'PCMU'], $device->switch_json['media']['audio']['codecs']);
    }

    public function test_invalid_device_type_and_unknown_nested_sip_field_return_422(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Unsupported endpoint',
                'device_type' => 'carrier_trunk',
                'is_enabled' => true,
                'sip' => [
                    'method' => 'password',
                    'password' => 'a-long-random-secret',
                    'private_auth_hash' => 'must-not-pass-validation',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_type', 'sip']);
    }

    public function test_device_name_cannot_exceed_the_switch_schema_limit(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => str_repeat('D', 129),
                'device_type' => 'sip_device',
                'is_enabled' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_maps_json_backed_device_fields_without_exposing_switch_resource_ids(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $media = SwitchMedia::factory()->for($account)->create([
            'id' => '2ec6914e-91aa-4b09-bbe7-7bf81631ebf7',
            'switch_resource_id' => 'switch-media-1',
            'name' => 'Office music',
        ]);
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, array $payload): bool => $receivedAccount->is($account)
                && $payload['music_on_hold']['media_id'] === 'switch-media-1'
                && $payload['outbound_flags']['static'] === ['fax']
                && $payload['sip']['custom_sip_headers']['out'][0]['name'] === 'X-Device'
                && $payload['dial_plan']['rules'][0]['pattern'] === '^([2-9][0-9]{6})$'
                && $payload['metaflows']['listen_on'] === 'both'
                && $payload['flags'] === ['crm_managed']
                && $payload['formatters'][0]['field'] === 'request'
                && $payload['provision']['check_sync_event'] === 'event')
            ->andReturn([
                'id' => 'switch-device-json-fields',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'music_on_hold' => ['media_id' => 'switch-media-1'],
                'outbound_flags' => ['static' => ['fax'], 'dynamic' => ['regional']],
                'sip' => [
                    'custom_sip_headers' => [
                        'in' => ['X-Source' => 'carrier'],
                        'out' => [
                            'X-Device' => 'reception',
                            'Authorization' => 'Bearer private-value',
                        ],
                    ],
                ],
                'dial_plan' => [
                    'system' => ['north_america'],
                    '^([2-9][0-9]{6})$' => ['description' => 'Local', 'prefix' => '+1555'],
                ],
                'metaflows' => [
                    'binding_digit' => '*',
                    'digit_timeout' => 2000,
                    'listen_on' => 'both',
                    'numbers' => ['1' => ['module' => 'transfer']],
                ],
                'flags' => ['crm_managed'],
                'formatters' => [
                    'request' => [[
                        'direction' => 'outbound',
                        'regex' => '^(.*)$',
                        'prefix' => '+1',
                    ]],
                ],
                'provision' => [
                    'check_sync_event' => 'event',
                    'check_sync_reload' => 'reload',
                    'check_sync_reboot' => 'reboot',
                ],
                'hotdesk' => ['users' => ['0123456789abcdef0123456789abcdef' => []]],
            ]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/accounts/{$account->id}/devices",
            [
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'is_enabled' => true,
                'music_on_hold' => ['media_id' => $media->id],
                'outbound_flags' => ['static' => ['fax'], 'dynamic' => ['regional']],
                'sip' => [
                    'custom_sip_headers' => [
                        'in' => [['name' => 'X-Source', 'value' => 'carrier']],
                        'out' => [['name' => 'X-Device', 'value' => 'reception']],
                    ],
                ],
                'dial_plan' => [
                    'system' => ['north_america'],
                    'rules' => [[
                        'pattern' => '^([2-9][0-9]{6})$',
                        'description' => 'Local',
                        'prefix' => '+1555',
                        'suffix' => null,
                    ]],
                ],
                'metaflows' => [
                    'binding_digit' => '*',
                    'digit_timeout' => 2000,
                    'listen_on' => 'both',
                ],
                'flags' => ['crm_managed'],
                'formatters' => [[
                    'field' => 'request',
                    'direction' => 'outbound',
                    'match_invite_format' => false,
                    'prefix' => '+1',
                    'regex' => '^(.*)$',
                    'strip' => false,
                    'suffix' => null,
                    'value' => null,
                ]],
                'provision' => [
                    'check_sync_event' => 'event',
                    'check_sync_reload' => 'reload',
                    'check_sync_reboot' => 'reboot',
                ],
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.configuration.music_on_hold.media_id', $media->id)
            ->assertJsonPath('data.configuration.music_on_hold.media_name', 'Office music')
            ->assertJsonPath('data.configuration.sip.custom_sip_headers.out.0.name', 'X-Device')
            ->assertJsonPath('data.configuration.dial_plan.rules.0.pattern', '^([2-9][0-9]{6})$')
            ->assertJsonPath('data.configuration.metaflows.number_flow_count', 1)
            ->assertJsonPath('data.configuration.metaflows.actions.0.trigger', '1')
            ->assertJsonPath('data.configuration.metaflows.actions.0.module', 'transfer')
            ->assertJsonPath('data.configuration.flags.0', 'crm_managed')
            ->assertJsonPath('data.configuration.formatters.0.field', 'request')
            ->assertJsonPath('data.configuration.formatters.0.direction', 'outbound')
            ->assertJsonPath('data.configuration.provision.check_sync_reboot', 'reboot')
            ->assertJsonPath('data.configuration.hotdesk.active_user_count', 1)
            ->assertDontSee('switch-media-1')
            ->assertDontSee('Bearer private-value')
            ->assertDontSee('0123456789abcdef0123456789abcdef');

        $projected = SwitchDevice::query()
            ->where('switch_resource_id', 'switch-device-json-fields')
            ->firstOrFail();
        $this->assertSame(
            '[REDACTED]',
            $projected->switch_json['sip']['custom_sip_headers']['out']['Authorization'],
        );
    }

    public function test_guided_metaflow_updates_replace_editable_actions_and_preserve_locked_actions(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-metaflows',
            'switch_json' => [
                'id' => 'switch-device-metaflows',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'metaflows' => [
                    'numbers' => [
                        '1' => ['module' => 'transfer', 'data' => ['target' => '1001']],
                        '9' => ['module' => 'callflow', 'data' => ['id' => 'private-callflow-id']],
                    ],
                ],
            ],
        ]);
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('update')
            ->once()
            ->withArgs(function (SwitchAccount $receivedAccount, string $resourceId, array $payload): bool {
                return $receivedAccount->getKey() !== null
                    && $resourceId === 'switch-device-metaflows'
                    && $payload['metaflows']['actions'][0]['trigger'] === '2'
                    && $payload['metaflows']['actions'][0]['module'] === 'hangup';
            })
            ->andReturn([
                'id' => 'switch-device-metaflows',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'metaflows' => [
                    'numbers' => [
                        '2' => ['module' => 'hangup', 'data' => [], 'children' => []],
                        '9' => ['module' => 'callflow', 'data' => ['id' => 'private-callflow-id']],
                    ],
                ],
            ]);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}", [
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'is_enabled' => true,
                'metaflows' => [
                    'binding_digit' => '*',
                    'listen_on' => 'both',
                    'actions' => [[
                        'trigger_type' => 'number',
                        'trigger' => '2',
                        'module' => 'hangup',
                        'data' => [],
                    ]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.configuration.metaflows.actions.0.trigger', '2')
            ->assertJsonPath('data.configuration.metaflows.locked_action_count', 1)
            ->assertDontSee('private-callflow-id');
    }

    public function test_it_maps_recursive_resource_linked_metaflows_to_public_ids_and_back(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $media = SwitchMedia::factory()->for($account)->create([
            'switch_resource_id' => 'switch-media-1',
            'name' => 'Greeting',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'switch-callflow-1',
            'name' => 'Main menu',
        ]);
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1',
            'display_name' => 'Alice Operator',
        ]);
        $destination = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-destination',
            'name' => 'Alice phone',
        ]);
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-source',
            'switch_json' => [
                'metaflows' => [
                    'numbers' => [
                        '1' => [
                            'module' => 'play',
                            'data' => ['id' => 'switch-media-1', 'leg' => 'both'],
                            'children' => [
                                'success' => [
                                    'module' => 'callflow',
                                    'data' => ['id' => 'switch-callflow-1'],
                                    'children' => [
                                        'continue' => [
                                            'module' => 'move',
                                            'data' => [
                                                'device_id' => 'switch-device-destination',
                                                'owner_id' => 'switch-user-1',
                                            ],
                                            'children' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/{$device->id}")
            ->assertOk()
            ->assertJsonPath('data.configuration.metaflows.actions.0.data.media_id', $media->id)
            ->assertJsonPath('data.configuration.metaflows.actions.0.children.0.key', 'success')
            ->assertJsonPath('data.configuration.metaflows.actions.0.children.0.data.callflow_id', $callflow->id)
            ->assertJsonPath('data.configuration.metaflows.actions.0.children.0.children.0.data.device_id', $destination->id)
            ->assertJsonPath('data.configuration.metaflows.actions.0.children.0.children.0.data.extension_id', $extension->id)
            ->assertJsonPath('data.configuration.metaflows.locked_action_count', 0);
        $response
            ->assertDontSee('switch-media-1')
            ->assertDontSee('switch-callflow-1')
            ->assertDontSee('switch-device-destination')
            ->assertDontSee('switch-user-1');

        $maps = app(DeviceMetaflowPolicy::class)->merge([], [[
            'trigger_type' => 'number',
            'trigger' => '1',
            'module' => 'play',
            'data' => ['media_id' => $media->id, 'leg' => 'both'],
            'children' => [[
                'key' => 'success',
                'module' => 'callflow',
                'data' => ['callflow_id' => $callflow->id],
                'children' => [[
                    'key' => 'continue',
                    'module' => 'move',
                    'data' => ['device_id' => $destination->id, 'extension_id' => $extension->id],
                    'children' => [],
                ]],
            ]],
        ]], $account);

        $this->assertSame('switch-media-1', $maps['numbers']['1']['data']['id']);
        $this->assertSame('switch-callflow-1', $maps['numbers']['1']['children']->success['data']['id']);
        $this->assertSame(
            'switch-device-destination',
            $maps['numbers']['1']['children']->success['children']->continue['data']['device_id'],
        );
        $this->assertSame(
            'switch-user-1',
            $maps['numbers']['1']['children']->success['children']->continue['data']['owner_id'],
        );
    }

    public function test_invalid_guided_metaflow_actions_are_rejected_before_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'is_enabled' => true,
                'metaflows' => [
                    'actions' => [
                        [
                            'trigger_type' => 'number',
                            'trigger' => '*1',
                            'module' => 'transfer',
                            'data' => ['private_id' => 'must-not-pass'],
                        ],
                        [
                            'trigger_type' => 'number',
                            'trigger' => '*1',
                            'module' => 'pivot',
                            'data' => [],
                        ],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'metaflows.actions.0.trigger',
                'metaflows.actions.0.data',
                'metaflows.actions.1.module',
                'metaflows.actions.1.trigger',
            ]);
    }

    public function test_it_sends_a_provisioning_sync_command_and_audits_it(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-provisioned',
            'device_type' => 'sip_device',
        ]);
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('sync')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, string $resourceId, bool $reboot): bool => $receivedAccount->is($account)
                && $resourceId === 'switch-device-provisioned'
                && $reboot === false);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/provisioning-sync", [
                'command' => 'sync',
            ])
            ->assertOk()
            ->assertJsonPath('data.command', 'sync')
            ->assertJsonPath('data.reboot', false)
            ->assertJsonPath('data.message', 'Switch accepted the device synchronization request.');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.provisioning_synchronized',
            'resource_id' => 'switch-device-provisioned',
            'outcome' => 'succeeded',
        ]);
    }

    public function test_it_sends_a_reprovision_command_and_audits_it(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-reprovisioned',
            'device_type' => 'sip_device',
        ]);
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('sync')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, string $resourceId, bool $reboot): bool => $receivedAccount->is($account)
                && $resourceId === 'switch-device-reprovisioned'
                && $reboot === true);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/provisioning-sync", [
                'command' => 'reprovision',
            ])
            ->assertOk()
            ->assertJsonPath('data.command', 'reprovision')
            ->assertJsonPath('data.reboot', true);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.provisioning_reprovisioned',
            'resource_id' => 'switch-device-reprovisioned',
            'outcome' => 'succeeded',
        ]);
    }

    public function test_it_requires_a_complete_provisioning_selection_and_mac_address(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Incomplete provisioned phone',
                'device_type' => 'sip_device',
                'provision' => [
                    'endpoint_brand' => 'yealink',
                    'endpoint_family' => null,
                    'endpoint_model' => 't54w',
                ],
                'mac_address' => null,
                'is_enabled' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['provision.endpoint_family', 'mac_address']);
    }

    public function test_it_rejects_a_model_outside_the_selected_provisioning_catalog_branch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchDeviceGateway::class);
        $gateway->shouldReceive('schemaCompatibility')
            ->once()
            ->andReturn($this->currentDeviceSchemaCompatibility());
        $gateway->shouldNotReceive('create');
        $this->mock(SwitchProvisioningCatalogGateway::class)
            ->shouldReceive('catalog')
            ->once()
            ->andReturn([
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
                        ]],
                    ]],
                ]],
            ]);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Unknown provisioned phone',
                'device_type' => 'sip_device',
                'provision' => [
                    'id' => 'yealink_t5_t54w',
                    'endpoint_brand' => 'yealink',
                    'endpoint_family' => 't5',
                    'endpoint_model' => 't99-unknown',
                ],
                'mac_address' => '00:11:22:33:44:66',
                'is_enabled' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provision.endpoint_model');
    }

    public function test_it_rejects_a_duplicate_mac_address_within_the_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        SwitchDevice::factory()->for($account)->create([
            'mac_address' => '00:11:22:33:44:55',
        ]);
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Duplicate desk phone',
                'device_type' => 'sip_device',
                'mac_address' => '00-11-22-33-44-55',
                'is_enabled' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mac_address')
            ->assertJsonPath(
                'errors.mac_address.0',
                'This MAC address is already assigned to another device in this account.',
            );
    }

    public function test_mac_addresses_are_canonicalized_and_scoped_to_an_account(): void
    {
        $firstAccount = SwitchAccount::factory()->create();
        $secondAccount = SwitchAccount::factory()->create();

        $first = SwitchDevice::factory()->for($firstAccount)->create([
            'mac_address' => 'aa-bb-cc-dd-ee-ff',
        ]);
        $second = SwitchDevice::factory()->for($secondAccount)->create([
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);

        $first->refresh();
        $second->refresh();

        $this->assertSame('AA:BB:CC:DD:EE:FF', $first->mac_address);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $second->mac_address);
        $this->assertSame('AABBCCDDEEFF', $first->active_mac_identity);

        $first->delete();
        $replacement = SwitchDevice::factory()->for($firstAccount)->create([
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);

        $this->assertSame('AA:BB:CC:DD:EE:FF', $replacement->mac_address);
    }

    public function test_it_lists_hotdesk_users_with_public_extension_ids_only(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-visible',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_json' => ['hotdesk' => ['users' => [
                'switch-user-visible' => [],
                'switch-user-unprojected' => [],
            ]]],
        ]);
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('addHotdeskUser', 'removeHotdeskUser');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/hotdesk-users")
            ->assertOk()
            ->assertJsonPath('data.users.0.id', $extension->id)
            ->assertJsonPath('data.users.0.display_name', 'Alice Operator')
            ->assertJsonPath('data.unresolved_count', 1)
            ->assertDontSee('switch-user-visible')
            ->assertDontSee('switch-user-unprojected');
    }

    public function test_it_signs_a_projected_extension_into_hotdesk_and_audits_the_operation(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-1',
            'switch_json' => ['id' => 'switch-device-1', 'hotdesk' => ['users' => []]],
        ]);
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('addHotdeskUser')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, string $deviceId, string $userId): bool => $receivedAccount->is($account)
                && $deviceId === 'switch-device-1'
                && $userId === 'switch-user-1')
            ->andReturn([
                'id' => 'switch-device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'hotdesk' => ['users' => ['switch-user-1' => []]],
            ]);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/hotdesk-users/{$extension->id}")
            ->assertOk()
            ->assertJsonPath('data.users.0.id', $extension->id)
            ->assertJsonPath('data.unresolved_count', 0)
            ->assertDontSee('switch-user-1');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.hotdesk_user_signed_in',
            'resource_id' => 'switch-device-1',
            'outcome' => 'succeeded',
        ]);
        $this->assertSame(
            ['switch-user-1'],
            array_keys($device->refresh()->switch_json['hotdesk']['users']),
        );
    }

    public function test_it_signs_a_projected_extension_out_of_hotdesk_and_audits_the_operation(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-1',
            'switch_json' => ['id' => 'switch-device-1', 'hotdesk' => ['users' => [
                'switch-user-1' => [],
            ]]],
        ]);
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('removeHotdeskUser')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, string $deviceId, string $userId): bool => $receivedAccount->is($account)
                && $deviceId === 'switch-device-1'
                && $userId === 'switch-user-1')
            ->andReturn([
                'id' => 'switch-device-1',
                'name' => 'Reception',
                'device_type' => 'sip_device',
                'enabled' => true,
                'hotdesk' => ['users' => []],
            ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/hotdesk-users/{$extension->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.users')
            ->assertJsonPath('data.unresolved_count', 0)
            ->assertDontSee('switch-user-1');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.hotdesk_user_signed_out',
            'resource_id' => 'switch-device-1',
            'outcome' => 'succeeded',
        ]);
        $this->assertSame([], $device->refresh()->switch_json['hotdesk']['users']);
    }

    public function test_it_rejects_hotdesk_mutation_for_an_extension_from_another_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create();
        $otherExtension = SwitchExtension::factory()->create();
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('addHotdeskUser');

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/hotdesk-users/{$otherExtension->id}")
            ->assertNotFound();
    }

    public function test_it_rejects_provisioning_commands_for_non_provisionable_device_types(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-cellphone',
            'device_type' => 'cellphone',
        ]);
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('sync');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/provisioning-sync", [
                'command' => 'sync',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device');
    }

    public function test_invalid_formatter_is_rejected_without_calling_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Bad formatter',
                'device_type' => 'sip_device',
                'is_enabled' => true,
                'formatters' => [[
                    'field' => 'invalid field',
                    'direction' => 'sideways',
                    'strip' => false,
                    'match_invite_format' => false,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['formatters.0.field', 'formatters.0.direction']);
    }

    public function test_invalid_recording_scope_returns_422_without_calling_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Recorded device',
                'device_type' => 'sip_device',
                'is_enabled' => true,
                'call_recording' => [
                    'inbound' => [
                        'offnet' => [
                            'enabled' => true,
                            'format' => 'flac',
                            'time_limit' => 20000,
                            'url' => 'http://127.0.0.1/private',
                        ],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'call_recording.inbound.offnet',
                'call_recording.inbound.offnet.format',
                'call_recording.inbound.offnet.time_limit',
            ]);
    }

    public function test_invalid_payload_does_not_call_the_upstream_gateway(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => '',
                'device_type' => '',
                'is_enabled' => true,
                'mac_address' => 'not-a-mac-address',
                'sip_password' => 'short',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'device_type', 'mac_address', 'sip_password'])
            ->assertJsonPath('errors.name.0', 'The name field is required.');
    }

    public function test_update_returns_404_for_a_device_outside_the_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $otherDevice = SwitchDevice::factory()->create();
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('update');

        $this->actingAs($user)->putJson(
            "/api/v1/accounts/{$account->id}/devices/{$otherDevice->id}",
            [
                'name' => 'Foreign Device',
                'device_type' => 'sip_device',
                'is_enabled' => true,
            ],
        )->assertNotFound();
    }

    public function test_delete_removes_the_upstream_device_and_soft_deletes_the_projection(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-device-1',
        ]);
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('delete')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, string $resourceId): bool => $receivedAccount->is($account)
                && $resourceId === 'switch-device-1');

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/devices/{$device->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($device);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.deleted',
            'resource_id' => 'switch-device-1',
            'outcome' => 'succeeded',
        ]);
    }

    public function test_upstream_failure_returns_a_safe_502_without_creating_a_projection(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('create')
            ->once()
            ->andThrow(new SwitchRequestException(
                'Upstream response contained sip_password=a-secret-value.',
                503,
                ['sip_password' => 'a-secret-value'],
            ));

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Reception Desk Phone',
                'device_type' => 'sip_device',
                'is_enabled' => true,
            ])
            ->assertStatus(502)
            ->assertExactJson(['message' => 'Switch is unavailable. Try again later.'])
            ->assertDontSee('a-secret-value');

        $this->assertDatabaseCount('switch_devices', 0);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.create_failed',
            'outcome' => 'failed',
        ]);
        $audit = AuditLog::query()->where('action', 'device.create_failed')->firstOrFail();
        $this->assertStringNotContainsString(
            'a-secret-value',
            json_encode($audit->metadata, JSON_THROW_ON_ERROR),
        );
    }

    public function test_read_only_user_receives_403_before_the_upstream_mutation(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices", [
                'name' => 'Reception Desk Phone',
                'device_type' => 'sip_device',
                'is_enabled' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(
        OrganizationRole $role = OrganizationRole::AccountOperator,
    ): array {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }

    /** @return array<string, mixed> */
    private function currentDeviceSchemaCompatibility(): array
    {
        return [
            'source' => 'connected_switch',
            'schema_id' => 'devices',
            'call_forward' => ['number_max_length' => 35],
            'sip' => [
                'invite_formats' => ['username', 'npan', '1npan', 'e164', 'route', 'strip_plus', 'contact'],
                'custom_sip_interface' => true,
                'forward' => true,
                'proxy' => true,
                'static_invite' => true,
                'transport' => true,
            ],
            'provision' => [
                'template_id' => true,
                'endpoint_model_types' => ['string', 'array'],
                'check_sync_event' => false,
                'check_sync_reload' => false,
                'check_sync_reboot' => false,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function legacyDeviceSchemaCompatibility(): array
    {
        return [
            'source' => 'bundled_legacy_fallback',
            'schema_id' => 'devices',
            'call_forward' => ['number_max_length' => 15],
            'sip' => [
                'invite_formats' => ['username', 'npan', '1npan', 'e164', 'route', 'contact'],
                'custom_sip_interface' => false,
                'forward' => false,
                'proxy' => false,
                'static_invite' => false,
                'transport' => false,
            ],
            'provision' => [
                'template_id' => false,
                'endpoint_model_types' => ['string', 'integer'],
                'check_sync_event' => true,
                'check_sync_reload' => true,
                'check_sync_reboot' => true,
            ],
        ];
    }
}
