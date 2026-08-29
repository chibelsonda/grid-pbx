<?php

namespace Tests\Feature\Domains\Devices;

use App\Domains\Devices\Contracts\ManufacturerProvisioningEnrollmentGateway;
use App\Domains\Devices\Contracts\SwitchProvisioningCatalogGateway;
use App\Domains\Devices\Enums\ProvisioningEnrollmentStatus;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Devices\Services\DeviceProvisioningEnrollmentService;
use App\Domains\Devices\Services\ProvisioningModelCapabilitiesService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DeviceProvisioningEnrollmentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_missing_legacy_enrollment_state_defaults_to_not_enrolled(): void
    {
        $device = new SwitchDevice([
            'device_type' => 'sip_device',
            'mac_address' => null,
        ]);
        $this->mock(ProvisioningModelCapabilitiesService::class)
            ->shouldReceive('forDevice')
            ->once()
            ->with($device)
            ->andReturn([
                'matched' => false,
                'max_keys' => null,
                'max_expansion_modules' => null,
                'keys_per_expansion_module' => null,
                'total_keys' => null,
                'supported_key_types' => [],
                'value_sources' => [],
                'manufacturer_provider' => null,
            ]);

        $state = app(DeviceProvisioningEnrollmentService::class)->status($device);

        $this->assertSame('not_enrolled', $state['status']);
        $this->assertFalse($state['can_enroll']);
        $this->assertFalse($state['can_detach']);
    }

    public function test_it_reports_eligible_but_disabled_enrollment_without_exposing_credentials(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = $this->eligibleDevice($account);
        $this->mockCatalog();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/provisioning-enrollment")
            ->assertOk()
            ->assertJsonPath('data.status', 'not_enrolled')
            ->assertJsonPath('data.provider', 'yealink-rps')
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.adapter_available', false)
            ->assertJsonPath('data.can_enroll', false)
            ->assertJsonPath('data.can_detach', false)
            ->assertJsonPath(
                'data.reason',
                'Manufacturer provisioning enrollment is disabled until the client provider contract and access configuration are available.',
            )
            ->assertJsonMissingPath('data.credentials')
            ->assertJsonMissingPath('data.token');
    }

    public function test_enrollment_requires_explicit_confirmation(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = $this->eligibleDevice($account);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/provisioning-enrollment")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmed');
    }

    public function test_disabled_adapter_rejects_enrollment_without_changing_local_state(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = $this->eligibleDevice($account);
        $this->mockCatalog();

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/provisioning-enrollment", [
                'confirmed' => true,
            ])
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'Manufacturer provisioning enrollment is disabled until the client provider contract and access configuration are available.',
            );

        $device->refresh();
        $this->assertSame(ProvisioningEnrollmentStatus::NotEnrolled, $device->provisioning_enrollment_status);
        $this->assertNull($device->provisioning_enrollment_provider);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_read_only_users_cannot_mutate_enrollment_state(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $device = $this->eligibleDevice($account);
        $this->mock(ManufacturerProvisioningEnrollmentGateway::class)->shouldNotReceive('enroll');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/provisioning-enrollment", [
                'confirmed' => true,
            ])
            ->assertForbidden();

        $device->refresh();
        $this->assertSame(ProvisioningEnrollmentStatus::NotEnrolled, $device->provisioning_enrollment_status);
    }

    public function test_available_adapter_enrolls_and_audits_only_safe_provider_metadata(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = $this->eligibleDevice($account);
        $this->mockCatalog();
        $gateway = $this->mock(ManufacturerProvisioningEnrollmentGateway::class);
        $gateway->shouldReceive('supports')->twice()->with('yealink-rps')->andReturnTrue();
        $gateway->shouldReceive('enroll')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, SwitchDevice $receivedDevice, string $provider): bool => $receivedAccount->is($account)
                && $receivedDevice->is($device)
                && $provider === 'yealink-rps');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/provisioning-enrollment", [
                'confirmed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.message', 'Device enrolled for manufacturer provisioning.')
            ->assertJsonPath('data.enrollment.status', 'enrolled')
            ->assertJsonPath('data.enrollment.provider', 'yealink-rps')
            ->assertJsonPath('data.enrollment.can_enroll', false)
            ->assertJsonPath('data.enrollment.can_detach', true);

        $device->refresh();
        $this->assertSame(ProvisioningEnrollmentStatus::Enrolled, $device->provisioning_enrollment_status);
        $this->assertSame('yealink-rps', $device->provisioning_enrollment_provider);
        $this->assertNotNull($device->provisioning_enrolled_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.provisioning_enrolled',
            'outcome' => 'succeeded',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'metadata->mac_address' => $device->mac_address,
        ]);
    }

    public function test_available_adapter_detaches_an_enrolled_device_after_confirmation(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $device = $this->eligibleDevice($account, [
            'provisioning_enrollment_status' => ProvisioningEnrollmentStatus::Enrolled,
            'provisioning_enrollment_provider' => 'yealink-rps',
            'provisioning_enrolled_at' => now()->subDay(),
        ]);
        $this->mockCatalog();
        $gateway = $this->mock(ManufacturerProvisioningEnrollmentGateway::class);
        $gateway->shouldReceive('supports')->twice()->with('yealink-rps')->andReturnTrue();
        $gateway->shouldReceive('detach')
            ->once()
            ->withArgs(fn (SwitchAccount $receivedAccount, SwitchDevice $receivedDevice, string $provider): bool => $receivedAccount->is($account)
                && $receivedDevice->is($device)
                && $provider === 'yealink-rps');

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/devices/{$device->id}/provisioning-enrollment", [
                'confirmed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.message', 'Device detached from manufacturer provisioning.')
            ->assertJsonPath('data.enrollment.status', 'not_enrolled')
            ->assertJsonPath('data.enrollment.provider', 'yealink-rps')
            ->assertJsonPath('data.enrollment.can_enroll', true)
            ->assertJsonPath('data.enrollment.can_detach', false);

        $device->refresh();
        $this->assertSame(ProvisioningEnrollmentStatus::NotEnrolled, $device->provisioning_enrollment_status);
        $this->assertNull($device->provisioning_enrollment_provider);
        $this->assertNull($device->provisioning_enrolled_at);
        $this->assertNotNull($device->provisioning_detached_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.provisioning_detached',
            'outcome' => 'succeeded',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function eligibleDevice(SwitchAccount $account, array $attributes = []): SwitchDevice
    {
        return SwitchDevice::factory()->for($account)->create($attributes + [
            'device_type' => 'sip_device',
            'make' => 'yealink',
            'endpoint_family' => 't5',
            'model' => 't54w',
            'mac_address' => '00:11:22:33:44:55',
        ]);
    }

    private function mockCatalog(): void
    {
        $this->mock(SwitchProvisioningCatalogGateway::class)
            ->shouldReceive('catalog')
            ->andReturn([
                'available' => true,
                'reason' => null,
                'brands' => [[
                    'id' => 'yealink',
                    'name' => 'Yealink',
                    'families' => [[
                        'id' => 't5',
                        'name' => 'T5 Series',
                        'models' => [[
                            'id' => 't54w',
                            'name' => 'T54W',
                            'template_id' => 'yealink_t5_t54w',
                            'manufacturer_provider' => 'yealink-rps',
                        ]],
                    ]],
                ]],
            ]);
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
