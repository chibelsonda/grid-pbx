<?php

namespace Tests\Feature\Domains\Devices;

use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
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

        $this->getJson("/api/v1/accounts/{$account->getKey()}/devices")
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
            ->getJson("/api/v1/accounts/{$account->getKey()}/devices?search=1001")
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
            ->getJson("/api/v1/accounts/{$account->getKey()}/devices")
            ->assertNotFound();
    }

    public function test_it_returns_device_details_without_the_source_payload(): void
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
            'source_payload' => ['sip' => ['password' => '[REDACTED]']],
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->getKey()}/devices/{$device->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Alice Desk Phone')
            ->assertJsonPath('data.device_type', 'sip_device')
            ->assertJsonPath('data.assigned_extension.id', $extension->getKey())
            ->assertJsonMissingPath('data.source_payload');
    }

    public function test_it_returns_404_when_the_device_belongs_to_another_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $otherDevice = SwitchDevice::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->getKey()}/devices/{$otherDevice->getKey()}")
            ->assertNotFound();
    }

    public function test_it_rejects_an_oversized_page(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->getKey()}/devices?per_page=101")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_unauthenticated_request_cannot_create_a_device(): void
    {
        $account = SwitchAccount::factory()->create();

        $this->postJson("/api/v1/accounts/{$account->getKey()}/devices", [])
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
            "/api/v1/accounts/{$account->getKey()}/devices",
            [
                'name' => 'Reception Desk Phone',
                'device_type' => 'sip_device',
                'make' => 'Yealink',
                'model' => 'T54W',
                'mac_address' => '00:11:22:33:44:55',
                'is_enabled' => true,
                'assigned_extension_id' => $extension->getKey(),
                'sip_username' => 'reception',
                'sip_password' => 'a-long-random-secret',
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Reception Desk Phone')
            ->assertJsonPath('data.assigned_extension.id', $extension->getKey())
            ->assertJsonMissingPath('data.source_payload')
            ->assertDontSee('a-long-random-secret');
        $device = SwitchDevice::query()->where('switch_resource_id', 'switch-device-1')->firstOrFail();
        $this->assertSame($extension->getKey(), $device->switch_extension_id);
        $this->assertSame('[REDACTED]', $device->source_payload['sip']['password']);
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
            "/api/v1/accounts/{$account->getKey()}/devices/{$device->getKey()}",
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
        $this->assertSame('[REDACTED]', $device->source_payload['sip']['password']);
    }

    public function test_invalid_payload_does_not_call_the_upstream_gateway(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchDeviceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->getKey()}/devices", [
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
            "/api/v1/accounts/{$account->getKey()}/devices/{$otherDevice->getKey()}",
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
            ->deleteJson("/api/v1/accounts/{$account->getKey()}/devices/{$device->getKey()}")
            ->assertNoContent();

        $this->assertSoftDeleted($device);
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
            ->postJson("/api/v1/accounts/{$account->getKey()}/devices", [
                'name' => 'Reception Desk Phone',
                'device_type' => 'sip_device',
                'is_enabled' => true,
            ])
            ->assertStatus(502)
            ->assertExactJson(['message' => 'Switch is unavailable. Try again later.'])
            ->assertDontSee('a-secret-value');

        $this->assertDatabaseCount('switch_devices', 0);
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_operator']);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
