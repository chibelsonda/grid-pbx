<?php

namespace Tests\Feature\Domains\Organizations;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_only_lists_accounts_available_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_operator']);
        $visible = SwitchAccount::factory()->for($organization)->create(['name' => 'Visible PBX']);
        SwitchAccount::factory()->create(['name' => 'Hidden PBX']);

        $this->actingAs($user)->getJson('/api/v1/accounts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.organization_role', 'account_operator')
            ->assertJsonPath('data.0.permissions.can_manage_devices', true)
            ->assertJsonMissing(['name' => 'Hidden PBX']);
    }

    public function test_it_keeps_a_disabled_member_account_visible_for_reactivation(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $disabled = SwitchAccount::factory()->for($organization)->create([
            'name' => 'Paused PBX',
            'is_enabled' => false,
        ]);

        $this->actingAs($user)->getJson('/api/v1/accounts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $disabled->id)
            ->assertJsonPath('data.0.enabled', false)
            ->assertJsonPath('data.0.permissions.can_manage_account_settings', true);
    }

    public function test_it_shows_a_safe_account_projection_with_resource_counts(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create([
            'name' => 'Grid Support',
            'realm' => 'support.example.test',
            'timezone' => 'Asia/Manila',
        ]);
        SwitchExtension::factory()->for($account)->create();
        SwitchDevice::factory()->for($account)->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $account->id)
            ->assertJsonPath('data.name', 'Grid Support')
            ->assertJsonPath('data.realm', 'support.example.test')
            ->assertJsonPath('data.timezone', 'Asia/Manila')
            ->assertJsonPath('data.resource_counts.extensions', 1)
            ->assertJsonPath('data.resource_counts.devices', 1)
            ->assertJsonPath('data.configuration_boundaries.billing_topup', 'provider_required')
            ->assertJsonMissingPath('data.account_id')
            ->assertJsonMissingPath('data.switch_account_id');
    }

    public function test_it_does_not_expose_an_account_from_another_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $foreign = SwitchAccount::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_an_account_administrator_can_update_safe_settings_and_project_a_redacted_snapshot(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create([
            'switch_account_id' => 'switch-account-1',
        ]);
        $externalNumber = SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15550001000',
        ]);
        $emergencyNumber = SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15550001911',
            'features' => ['e911'],
        ]);
        $gateway = $this->mock(SwitchAccountGateway::class);
        $gateway->shouldReceive('updateSettings')
            ->once()
            ->withArgs(fn (SwitchAccount $received, array $data): bool => $received->is($account)
                && $data['name'] === 'Grid Support'
                && $data['outbound_privacy'] === 'number'
                && $data['caller_id']['external']['number'] === '+15550001000'
                && $data['caller_id']['emergency']['number'] === '+15550001911'
                && ! array_key_exists('phone_number_id', $data['caller_id']['external']))
            ->andReturn($this->accountSnapshot(['password' => 'must-not-be-stored']));

        $response = $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}", [
            'name' => 'Grid Support',
            'organization_name' => 'Grid Corp',
            'timezone' => 'Asia/Manila',
            'language' => 'en-US',
            'call_waiting_enabled' => false,
            'do_not_disturb_enabled' => true,
            'outbound_privacy' => 'number',
            'show_rate' => true,
            'ringtone_internal' => 'ring-1',
            'ringtone_external' => 'ring-2',
            'caller_id' => [
                'internal' => ['name' => 'Support', 'number' => '1000'],
                'external' => [
                    'name' => 'Grid Support',
                    'phone_number_id' => $externalNumber->id,
                    'preserve_number' => false,
                ],
                'emergency' => [
                    'name' => 'Grid Emergency',
                    'phone_number_id' => $emergencyNumber->id,
                    'preserve_number' => false,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Grid Support')
            ->assertJsonPath('data.configuration.do_not_disturb_enabled', true)
            ->assertJsonPath('data.projection.status', 'synced')
            ->assertJsonPath('data.projection.version', 1)
            ->assertJsonPath('data.permissions.can_manage_settings', true)
            ->assertJsonPath('data.configuration.caller_id.external.phone_number_id', $externalNumber->id)
            ->assertJsonPath('data.configuration.caller_id.emergency.phone_number_id', $emergencyNumber->id)
            ->assertJsonPath('data.options.caller_id_numbers.0.id', $externalNumber->id)
            ->assertJsonMissingPath('data.switch_json')
            ->assertJsonMissingPath('data.switch_account_id');
        $account->refresh();
        $this->assertSame('[REDACTED]', $account->switch_json['password']);
        $this->assertSame('Grid Corp', $account->org_name);
        $this->assertTrue($account->do_not_disturb_enabled);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.settings_updated',
            'outcome' => 'succeeded',
            'resource_type' => 'account',
        ]);
    }

    public function test_an_account_operator_cannot_update_account_settings(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_operator']);
        $account = SwitchAccount::factory()->for($organization)->create();
        $this->mock(SwitchAccountGateway::class)->shouldNotReceive('updateSettings');

        $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}", [
            'name' => 'Grid Support',
            'organization_name' => null,
            'timezone' => null,
            'language' => null,
            'call_waiting_enabled' => true,
            'do_not_disturb_enabled' => false,
            'outbound_privacy' => 'none',
            'show_rate' => false,
            'ringtone_internal' => null,
            'ringtone_external' => null,
            'caller_id' => [
                'internal' => ['name' => null, 'number' => null],
                'external' => ['name' => null, 'phone_number_id' => null, 'preserve_number' => false],
                'emergency' => ['name' => null, 'phone_number_id' => null, 'preserve_number' => false],
            ],
        ])->assertForbidden();
    }

    public function test_it_rejects_invalid_account_settings_with_field_messages(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create();
        $this->mock(SwitchAccountGateway::class)->shouldNotReceive('updateSettings');

        $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}", [
            'name' => '',
            'call_waiting_enabled' => true,
            'do_not_disturb_enabled' => false,
            'outbound_privacy' => 'secret',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'outbound_privacy'])
            ->assertJsonPath('errors.name.0', 'Enter an account name.')
            ->assertJsonPath('errors.outbound_privacy.0', 'Select a valid outbound privacy policy.');
    }

    public function test_it_rejects_an_emergency_caller_id_without_e911(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create();
        $number = SwitchPhoneNumber::factory()->for($account)->create(['features' => ['local']]);
        $this->mock(SwitchAccountGateway::class)->shouldNotReceive('updateSettings');

        $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}", [
            'name' => 'Grid Support',
            'organization_name' => null,
            'timezone' => null,
            'language' => null,
            'call_waiting_enabled' => true,
            'do_not_disturb_enabled' => false,
            'outbound_privacy' => 'none',
            'show_rate' => false,
            'ringtone_internal' => null,
            'ringtone_external' => null,
            'caller_id' => [
                'internal' => ['name' => null, 'number' => null],
                'external' => ['name' => null, 'phone_number_id' => null, 'preserve_number' => false],
                'emergency' => [
                    'name' => 'Emergency',
                    'phone_number_id' => $number->id,
                    'preserve_number' => false,
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['caller_id.emergency.phone_number_id'])
            ->assertJsonFragment(['Select a phone number with E911 enabled.']);
    }

    public function test_an_account_administrator_can_refresh_the_account_projection(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create([
            'switch_account_id' => 'switch-account-1',
        ]);
        $this->mock(SwitchAccountGateway::class)
            ->shouldReceive('find')
            ->once()
            ->andReturn($this->accountSnapshot());

        $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/sync")
            ->assertOk()
            ->assertJsonPath('data.projection.status', 'synced')
            ->assertJsonPath('data.projection.version', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.settings_refreshed',
            'outcome' => 'succeeded',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function accountSnapshot(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'switch-account-1',
            'name' => 'Grid Support',
            'org' => 'Grid Corp',
            'realm' => 'support.example.test',
            'timezone' => 'Asia/Manila',
            'language' => 'en-US',
            'enabled' => true,
            'call_waiting' => ['enabled' => false],
            'do_not_disturb' => ['enabled' => true],
            'caller_id_options' => ['outbound_privacy' => 'number'],
            'caller_id' => [
                'internal' => ['name' => 'Support', 'number' => '1000'],
                'external' => ['name' => 'Grid Support', 'number' => '+15550001000'],
                'emergency' => ['name' => 'Grid Emergency', 'number' => '+15550001911'],
            ],
            'ringtones' => ['internal' => 'ring-1', 'external' => 'ring-2'],
        ], $overrides);
    }

    public function test_an_account_administrator_can_disable_and_still_view_an_account(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create([
            'switch_account_id' => 'switch-account-1',
            'name' => 'Grid Support',
        ]);
        $this->mock(SwitchAccountGateway::class)
            ->shouldReceive('updateEnabled')
            ->once()
            ->withArgs(fn (SwitchAccount $received, bool $enabled): bool => $received->is($account) && ! $enabled)
            ->andReturn($this->accountSnapshot(['enabled' => false]));

        $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}/status", [
            'enabled' => false,
            'confirmation' => 'Grid Support',
        ])->assertOk()
            ->assertJsonPath('data.enabled', false);
        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.disabled',
            'outcome' => 'succeeded',
        ]);
    }

    public function test_account_status_requires_the_exact_account_name_confirmation(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create(['name' => 'Grid Support']);
        $this->mock(SwitchAccountGateway::class)->shouldNotReceive('updateEnabled');

        $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}/status", [
            'enabled' => false,
            'confirmation' => 'grid support',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.confirmation.0', 'Enter the account name exactly to confirm this operation.');
    }
}
