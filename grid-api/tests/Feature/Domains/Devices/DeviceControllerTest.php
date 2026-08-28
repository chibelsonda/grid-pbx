<?php

namespace Tests\Feature\Domains\Devices;

use App\Domains\Auditing\Models\AuditLog;
use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use GridPbx\Switch\Exceptions\SwitchRequestException;
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
            'switch_json' => ['sip' => ['password' => '[REDACTED]']],
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
            ->assertJsonMissingPath('data.switch_json');
    }

    public function test_it_returns_404_when_the_device_belongs_to_another_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $otherDevice = SwitchDevice::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/{$otherDevice->id}")
            ->assertNotFound();
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
        $this->mock(SwitchDeviceGateway::class)
            ->shouldReceive('restrictionClassifiers')
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
            ->assertJsonPath('data.restrictions.0.key', 'tollfree_us')
            ->assertJsonPath('data.restrictions.1.emergency', true)
            ->assertJsonMissingPath('data.restrictions.0.regex')
            ->assertDontSee('switch-user-1');
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
                && $payload['metaflows']['listen_on'] === 'both')
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
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.configuration.music_on_hold.media_id', $media->id)
            ->assertJsonPath('data.configuration.music_on_hold.media_name', 'Office music')
            ->assertJsonPath('data.configuration.sip.custom_sip_headers.out.0.name', 'X-Device')
            ->assertJsonPath('data.configuration.dial_plan.rules.0.pattern', '^([2-9][0-9]{6})$')
            ->assertJsonPath('data.configuration.metaflows.number_flow_count', 1)
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
}
