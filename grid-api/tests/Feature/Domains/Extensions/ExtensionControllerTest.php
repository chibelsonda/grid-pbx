<?php

namespace Tests\Feature\Domains\Extensions;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Contracts\SwitchExtensionProvisioningGateway;
use App\Domains\Extensions\Exceptions\ExtensionProvisioningException;
use App\Domains\Extensions\Models\ExtensionLifecycleOperation;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ExtensionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_lists_and_searches_projected_extensions_for_an_accessible_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Bob Support',
            'extension' => '1002',
        ]);

        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/extensions?search=Alice")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.extension', '1001');
    }

    public function test_it_hides_extensions_from_users_outside_the_account_organization(): void
    {
        $user = User::factory()->create();
        $account = SwitchAccount::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/extensions")
            ->assertNotFound();
    }

    public function test_it_returns_projected_devices_voicemail_and_callflows_for_an_extension(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        SwitchDevice::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'owner_switch_resource_id' => 'switch-user-1',
            'name' => 'Alice Desk Phone',
        ]);
        SwitchVoicemailBox::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'owner_switch_resource_id' => 'switch-user-1',
            'mailbox' => '1001',
        ]);
        SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'owner_switch_resource_id' => 'switch-user-1',
            'name' => 'Alice Callflow',
            'numbers' => ['1001'],
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}")
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Alice Operator')
            ->assertJsonPath('data.devices.0.name', 'Alice Desk Phone')
            ->assertJsonPath('data.voicemail_boxes.0.mailbox', '1001')
            ->assertJsonPath('data.callflows.0.numbers.0', '1001');
    }

    public function test_it_returns_404_when_the_extension_belongs_to_another_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $otherExtension = SwitchExtension::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/extensions/{$otherExtension->id}")
            ->assertNotFound();
    }

    public function test_it_provisions_a_managed_extension_and_its_selected_related_resources(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchExtensionProvisioningGateway::class);
        $gateway->shouldReceive('createUser')->once()->withArgs(
            fn ($providedAccount, array $data): bool => $providedAccount->is($account)
                && $data['language'] === 'en-US'
                && $data['presence_id'] === 'alice@pbx.example.test'
                && $data['call_waiting']['enabled'] === false
                && $data['do_not_disturb']['enabled'] === true
                && $data['contact_list']['exclude'] === true
                && $data['caller_id_options']['outbound_privacy'] === 'name'
                && $data['hotdesk']['enabled'] === true
                && $data['hotdesk']['id'] === '1001'
                && $data['hotdesk']['pin'] === '2468'
                && $data['password'] === 'correct-horse-battery-staple'
                && $data['require_password_update'] === true
                && ! array_key_exists('password_confirmation', $data),
        )->andReturn([
            'id' => 'switch-user-1001',
            'first_name' => 'Alice',
            'last_name' => 'Operator',
            'username' => 'alice.operator',
            'password' => 'correct-horse-battery-staple',
            'require_password_update' => true,
            'email' => 'alice@example.test',
            'timezone' => 'Asia/Manila',
            'enabled' => true,
            'caller_id' => ['internal' => ['name' => 'Alice Operator', 'number' => '1001']],
            'presence_id' => 'alice@pbx.example.test',
            'language' => 'en-US',
            'call_waiting' => ['enabled' => false],
            'do_not_disturb' => ['enabled' => true],
            'contact_list' => ['exclude' => true],
            'caller_id_options' => ['outbound_privacy' => 'name'],
            'hotdesk' => [
                'enabled' => true,
                'id' => '1001',
                'keep_logged_in_elsewhere' => true,
                'require_pin' => true,
                'pin' => '2468',
            ],
        ]);
        $gateway->shouldReceive('createVoicemailBox')->once()->withArgs(
            fn ($providedAccount, array $data): bool => $providedAccount->is($account)
                && $data['owner_id'] === 'switch-user-1001'
                && $data['mailbox'] === '1001',
        )->andReturn([
            'id' => 'switch-vmbox-1001',
            'owner_id' => 'switch-user-1001',
            'name' => '(1001) Alice Operator',
            'mailbox' => '1001',
            'timezone' => 'Asia/Manila',
            'notify_email_addresses' => ['alice@example.test'],
            'transcribe' => false,
            'require_pin' => true,
            'pin' => '1234',
        ]);
        $gateway->shouldReceive('createDevice')->once()->withArgs(
            fn ($providedAccount, array $data): bool => $providedAccount->is($account)
                && $data['owner_id'] === 'switch-user-1001'
                && $data['name'] === 'Alice desk phone',
        )->andReturn([
            'id' => 'switch-device-1001',
            'owner_id' => 'switch-user-1001',
            'name' => 'Alice desk phone',
            'device_type' => 'sip_device',
            'enabled' => true,
            'sip' => ['username' => 'alice-1001', 'password' => 'do-not-project-this-secret'],
        ]);
        $gateway->shouldReceive('createManagedCallflow')->once()->withArgs(
            fn ($providedAccount, string $name, string $extension, string $userId, ?string $voicemailId): bool => $providedAccount->is($account)
                && $name === 'Alice Operator'
                && $extension === '1001'
                && $userId === 'switch-user-1001'
                && $voicemailId === 'switch-vmbox-1001',
        )->andReturn([
            'id' => 'switch-callflow-1001',
            'name' => 'Alice Operator',
            'numbers' => ['1001'],
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'switch-user-1001'],
                'children' => [
                    '_' => [
                        'module' => 'voicemail',
                        'data' => ['id' => 'switch-vmbox-1001'],
                        'children' => [],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/accounts/{$account->id}/extensions",
            $this->extensionPayload(),
        );

        $response->assertCreated()
            ->assertJsonPath('data.display_name', 'Alice Operator')
            ->assertJsonPath('data.extension', '1001')
            ->assertJsonPath('data.devices.0.name', 'Alice desk phone')
            ->assertJsonPath('data.voicemail_boxes.0.mailbox', '1001')
            ->assertJsonPath('data.configuration.language', 'en-US')
            ->assertJsonPath('data.configuration.presence_id', 'alice@pbx.example.test')
            ->assertJsonPath('data.configuration.call_waiting.enabled', false)
            ->assertJsonPath('data.configuration.do_not_disturb.enabled', true)
            ->assertJsonPath('data.configuration.contact_list.exclude', true)
            ->assertJsonPath('data.configuration.caller_id_options.outbound_privacy', 'name')
            ->assertJsonPath('data.configuration.credentials.password_configured', true)
            ->assertJsonPath('data.configuration.credentials.require_password_update', true)
            ->assertJsonPath('data.configuration.hotdesk.enabled', true)
            ->assertJsonPath('data.configuration.hotdesk.id', '1001')
            ->assertJsonPath('data.configuration.hotdesk.keep_logged_in_elsewhere', true)
            ->assertJsonPath('data.configuration.hotdesk.require_pin', true)
            ->assertJsonPath('data.configuration.hotdesk.pin_configured', true)
            ->assertJsonPath('data.callflows.0.modules.0', 'user')
            ->assertJsonPath('data.callflows.0.modules.1', 'voicemail');
        $extension = SwitchExtension::query()->where('switch_resource_id', 'switch-user-1001')->firstOrFail();
        $this->assertTrue($extension->is_managed);
        $this->assertSame('extension_provisioning', $extension->managed_by_workflow);
        $this->assertSame('en-US', $extension->switch_json['language']);
        $this->assertTrue($extension->switch_json['do_not_disturb']['enabled']);
        $this->assertSame('[REDACTED]', $extension->switch_json['hotdesk']['pin']);
        $this->assertSame('[REDACTED]', $extension->switch_json['password']);
        $this->assertTrue($extension->devices()->firstOrFail()->is_managed);
        $this->assertSame('[REDACTED]', $extension->devices()->firstOrFail()->switch_json['sip']['password']);
        $this->assertTrue($extension->voicemailBoxes()->firstOrFail()->is_managed);
        $this->assertSame('[REDACTED]', $extension->voicemailBoxes()->firstOrFail()->switch_json['pin']);
        $this->assertTrue($extension->callflows()->firstOrFail()->is_managed);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'extension.provisioned',
            'resource_type' => 'extension',
            'outcome' => 'succeeded',
        ]);
        $operation = ExtensionLifecycleOperation::query()->where('operation', 'provision')->sole();
        $this->assertSame('succeeded', $operation->status);
        $this->assertSame($extension->getKey(), $operation->switch_extension_id);
        $this->assertStringNotContainsString('1234', json_encode($operation->context, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('2468', json_encode($operation->context, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('a-long-random-secret', json_encode($operation->context, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('correct-horse-battery-staple', $response->getContent());
    }

    public function test_it_compensates_in_reverse_order_when_related_resource_creation_fails(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchExtensionProvisioningGateway::class);
        $compensated = [];
        $gateway->shouldReceive('createUser')->once()->andReturn([
            'id' => 'switch-user-1001',
            'first_name' => 'Alice',
            'last_name' => 'Operator',
            'caller_id' => ['internal' => ['number' => '1001']],
        ]);
        $gateway->shouldReceive('createVoicemailBox')->once()->andReturn([
            'id' => 'switch-vmbox-1001',
            'owner_id' => 'switch-user-1001',
            'name' => '(1001) Alice Operator',
            'mailbox' => '1001',
        ]);
        $gateway->shouldReceive('createDevice')->once()->andThrow(new RuntimeException('Device failed.'));
        $gateway->shouldReceive('deleteVoicemailBox')
            ->once()->withArgs(fn ($providedAccount, string $resourceId): bool => $providedAccount->is($account)
                && $resourceId === 'switch-vmbox-1001')
            ->andReturnUsing(function (...$arguments) use (&$compensated): void {
                $compensated[] = 'voicemail_box';
            });
        $gateway->shouldReceive('deleteUser')
            ->once()->withArgs(fn ($providedAccount, string $resourceId): bool => $providedAccount->is($account)
                && $resourceId === 'switch-user-1001')
            ->andReturnUsing(function (...$arguments) use (&$compensated): void {
                $compensated[] = 'user';
            });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->postJson(
                "/api/v1/accounts/{$account->id}/extensions",
                $this->extensionPayload(),
            );
            $this->fail('Expected extension provisioning to fail.');
        } catch (ExtensionProvisioningException $exception) {
            $this->assertSame([], $exception->compensationFailures);
            $this->assertStringContainsString('created resources were removed', $exception->getMessage());
            $this->assertSame(['voicemail_box', 'user'], $compensated);
            $this->assertSame('rolled_back', ExtensionLifecycleOperation::query()->sole()->status);
        }
    }

    public function test_read_only_users_cannot_provision_extensions(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'read_only_user']);
        $account = SwitchAccount::factory()->for($organization)->create();
        $this->mock(SwitchExtensionProvisioningGateway::class)->shouldNotReceive('createUser');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/extensions", $this->extensionPayload())
            ->assertForbidden();
    }

    public function test_invalid_user_calling_options_return_422_without_calling_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchExtensionProvisioningGateway::class)->shouldNotReceive('createUser');
        $payload = $this->extensionPayload();
        $payload['caller_id_options']['outbound_privacy'] = 'secret';
        $payload['do_not_disturb']['private_state'] = true;
        $payload['hotdesk']['id'] = 'abc';
        $payload['hotdesk']['pin'] = '12ab';
        $payload['device']['device_type'] = 'carrier_trunk';

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/extensions", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'caller_id_options.outbound_privacy',
                'do_not_disturb',
                'hotdesk.id',
                'hotdesk.pin',
                'device.device_type',
            ]);
    }

    public function test_create_rejects_a_device_mac_address_already_used_by_the_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        SwitchDevice::factory()->for($account)->create([
            'mac_address' => '00:11:22:33:44:55',
        ]);
        $this->mock(SwitchExtensionProvisioningGateway::class)->shouldNotReceive('createUser');
        $payload = $this->extensionPayload();
        $payload['device']['mac_address'] = '00-11-22-33-44-55';

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/extensions", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device.mac_address');
    }

    public function test_update_returns_422_when_pin_protection_has_no_new_or_configured_hotdesk_pin(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
            'switch_json' => ['hotdesk' => ['enabled' => true, 'id' => '1001']],
        ]);
        $this->mock(SwitchExtensionProvisioningGateway::class)->shouldNotReceive('updateUser');

        $this->actingAs($user)
            ->putJson(
                "/api/v1/accounts/{$account->id}/extensions/{$extension->id}",
                $this->updatePayload(),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hotdesk.pin');
    }

    public function test_create_returns_422_when_login_password_is_missing(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchExtensionProvisioningGateway::class)->shouldNotReceive('createUser');
        $payload = $this->extensionPayload();
        $payload['password'] = null;
        $payload['password_confirmation'] = null;

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/extensions", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_create_returns_422_when_login_password_is_not_confirmed(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchExtensionProvisioningGateway::class)->shouldNotReceive('createUser');
        $payload = $this->extensionPayload();
        $payload['password_confirmation'] = 'different-password';

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/extensions", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_update_returns_422_when_login_username_changes_without_a_password(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'username' => 'alice.operator',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
            'switch_json' => ['hotdesk' => ['enabled' => true, 'id' => '1001', 'require_pin' => true, 'pin' => '[REDACTED]']],
        ]);
        $this->mock(SwitchExtensionProvisioningGateway::class)->shouldNotReceive('updateUser');
        $payload = $this->updatePayload();
        $payload['username'] = 'alice.changed';
        $payload['password'] = null;
        $payload['password_confirmation'] = null;

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_update_returns_422_when_configured_login_removal_is_not_confirmed(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'username' => 'alice.operator',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
            'switch_json' => ['hotdesk' => ['enabled' => true, 'id' => '1001', 'require_pin' => true, 'pin' => '[REDACTED]']],
        ]);
        $this->mock(SwitchExtensionProvisioningGateway::class)->shouldNotReceive('updateUser');
        $payload = $this->updatePayload();
        $payload['username'] = null;

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_it_updates_a_managed_extension_mailbox_and_callflow_as_one_workflow(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1001',
            'first_name' => 'Alice',
            'last_name' => 'Operator',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
            'username' => 'alice.support',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
            'switch_json' => [
                'hotdesk' => ['enabled' => true, 'id' => '1001', 'require_pin' => true, 'pin' => '[REDACTED]'],
            ],
        ]);
        $voicemail = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'switch_resource_id' => 'switch-vmbox-1001',
            'owner_switch_resource_id' => 'switch-user-1001',
            'mailbox' => '1001',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'switch_resource_id' => 'switch-callflow-1001',
            'owner_switch_resource_id' => 'switch-user-1001',
            'numbers' => ['1001'],
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        $gateway = $this->mock(SwitchExtensionProvisioningGateway::class);
        $gateway->shouldReceive('updateUser')->once()->withArgs(
            fn ($providedAccount, string $resourceId, array $data): bool => $providedAccount->is($account)
                && $resourceId === 'switch-user-1001'
                && $data['language'] === 'en-US'
                && $data['presence_id'] === 'alice@pbx.example.test'
                && $data['call_waiting']['enabled'] === false
                && $data['do_not_disturb']['enabled'] === true
                && $data['contact_list']['exclude'] === true
                && $data['caller_id_options']['outbound_privacy'] === 'name'
                && $data['hotdesk']['pin'] === null
                && $data['hotdesk']['clear_pin'] === false
                && $data['password'] === null
                && $data['require_password_update'] === false
                && ! array_key_exists('password_confirmation', $data),
        )->andReturn([
            'id' => 'switch-user-1001',
            'first_name' => 'Alice',
            'last_name' => 'Support',
            'username' => 'alice.support',
            'require_password_update' => false,
            'email' => 'alice@example.test',
            'timezone' => 'Asia/Manila',
            'enabled' => true,
            'caller_id' => ['internal' => ['name' => 'Alice Support', 'number' => '1010']],
            'presence_id' => 'alice@pbx.example.test',
            'language' => 'en-US',
            'call_waiting' => ['enabled' => false],
            'do_not_disturb' => ['enabled' => true],
            'contact_list' => ['exclude' => true],
            'caller_id_options' => ['outbound_privacy' => 'name'],
            'hotdesk' => [
                'enabled' => true,
                'id' => '1001',
                'keep_logged_in_elsewhere' => true,
                'require_pin' => true,
                'pin' => '2468',
            ],
        ]);
        $gateway->shouldReceive('updateVoicemailBox')->once()->withArgs(
            fn ($providedAccount, string $resourceId, array $data): bool => $providedAccount->is($account)
                && $resourceId === $voicemail->switch_resource_id
                && $data['mailbox'] === '1010'
                && $data['require_pin'] === true
                && $data['pin'] === null,
        )->andReturn([
            'id' => 'switch-vmbox-1001',
            'owner_id' => 'switch-user-1001',
            'name' => '(1010) Alice Support',
            'mailbox' => '1010',
            'timezone' => 'Asia/Manila',
            'notify_email_addresses' => ['alice@example.test'],
            'transcribe' => true,
            'require_pin' => true,
        ]);
        $gateway->shouldReceive('updateManagedCallflow')->once()->withArgs(
            fn ($providedAccount, string $resourceId, string $userId, string $previous, string $next, string $name, ?string $voicemailId): bool => $providedAccount->is($account)
                && $resourceId === $callflow->switch_resource_id
                && $userId === 'switch-user-1001'
                && $previous === '1001'
                && $next === '1010'
                && $name === 'Alice Support'
                && $voicemailId === 'switch-vmbox-1001',
        )->andReturn([
            'id' => 'switch-callflow-1001',
            'name' => 'Alice Support',
            'numbers' => ['1010'],
            'flow' => [
                'module' => 'user',
                'data' => ['id' => 'switch-user-1001'],
                'children' => [
                    '_' => [
                        'module' => 'voicemail',
                        'data' => ['id' => 'switch-vmbox-1001'],
                        'children' => [],
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}", $this->updatePayload())
            ->assertOk()
            ->assertJsonPath('data.id', $extension->id)
            ->assertJsonPath('data.display_name', 'Alice Support')
            ->assertJsonPath('data.extension', '1010')
            ->assertJsonPath('data.configuration.do_not_disturb.enabled', true)
            ->assertJsonPath('data.configuration.credentials.password_configured', true)
            ->assertJsonPath('data.configuration.credentials.require_password_update', false)
            ->assertJsonPath('data.configuration.hotdesk.pin_configured', true)
            ->assertJsonPath('data.voicemail_boxes.0.mailbox', '1010')
            ->assertJsonPath('data.callflows.0.numbers.0', '1010');
        $this->assertDatabaseHas('switch_extensions', [
            'extension_id' => $extension->getKey(),
            'id' => $extension->id,
            'extension' => '1010',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'extension.updated', 'outcome' => 'succeeded']);
        $this->assertDatabaseHas('extension_lifecycle_operations', [
            'switch_extension_id' => $extension->getKey(),
            'operation' => 'update',
            'status' => 'succeeded',
        ]);
    }

    public function test_it_blocks_disabling_a_managed_mailbox_that_contains_messages(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
            'switch_json' => [
                'hotdesk' => ['enabled' => true, 'id' => '1001', 'require_pin' => true, 'pin' => '[REDACTED]'],
            ],
        ]);
        $voicemail = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        SwitchVoicemailMessage::factory()->for($account)->for($voicemail, 'voicemailBox')->create();
        SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        $this->mock(SwitchExtensionProvisioningGateway::class)->shouldNotReceive('updateUser');
        $payload = $this->updatePayload();
        $payload['extension'] = $extension->extension;
        $payload['voicemail']['enabled'] = false;

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('voicemail.enabled');
    }

    public function test_it_previews_safe_managed_deletion_without_exposing_upstream_ids(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        SwitchDevice::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}/deletion-preview")
            ->assertOk()
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonCount(0, 'data.blockers')
            ->assertJsonCount(1, 'data.managed_resources.devices')
            ->assertJsonCount(1, 'data.managed_resources.callflows');

        $this->assertStringNotContainsString('switch_resource_id', $response->getContent());
        $this->assertStringNotContainsString($extension->switch_resource_id, $response->getContent());
    }

    public function test_deletion_preview_reports_shared_voicemail_and_callflow_reference_blockers(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        SwitchVoicemailBox::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'is_managed' => false,
        ]);
        SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        SwitchCallflow::factory()->for($account)->create([
            'flow_structure' => [
                'module' => 'user',
                'target' => ['type' => 'extension', 'id' => $extension->id, 'label' => $extension->display_name],
                'reference_status' => 'resolved',
                'children' => [],
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}/deletion-preview")
            ->assertOk()
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.shared_resources.voicemail_box_count', 1)
            ->assertJsonCount(1, 'data.referencing_callflows');
        $codes = collect($response->json('data.blockers'))->pluck('code');
        $this->assertTrue($codes->contains('shared_voicemail'));
        $this->assertTrue($codes->contains('referenced_by_callflow'));
    }

    public function test_it_deletes_only_managed_extension_resources_in_reverse_dependency_order(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-delete',
            'extension' => '1099',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'switch_resource_id' => 'switch-callflow-delete',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'switch_resource_id' => 'switch-device-delete',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        $voicemail = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'switch_resource_id' => 'switch-vmbox-delete',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        $gateway = $this->mock(SwitchExtensionProvisioningGateway::class);
        $gateway->shouldReceive('deleteCallflow')->once()->ordered()->withArgs(
            fn ($providedAccount, string $resourceId): bool => $providedAccount->is($account)
                && $resourceId === 'switch-callflow-delete',
        );
        $gateway->shouldReceive('deleteDevice')->once()->ordered()->withArgs(
            fn ($providedAccount, string $resourceId): bool => $providedAccount->is($account)
                && $resourceId === 'switch-device-delete',
        );
        $gateway->shouldReceive('deleteVoicemailBox')->once()->ordered()->withArgs(
            fn ($providedAccount, string $resourceId): bool => $providedAccount->is($account)
                && $resourceId === 'switch-vmbox-delete',
        );
        $gateway->shouldReceive('deleteUser')->once()->ordered()->withArgs(
            fn ($providedAccount, string $resourceId): bool => $providedAccount->is($account)
                && $resourceId === 'switch-user-delete',
        );

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}", [
                'confirmation' => '1099',
            ])
            ->assertNoContent();

        $this->assertSoftDeleted('switch_callflows', ['callflow_id' => $callflow->getKey()]);
        $this->assertSoftDeleted('switch_devices', ['device_id' => $device->getKey()]);
        $this->assertSoftDeleted('switch_voicemail_boxes', ['voicemail_box_id' => $voicemail->getKey()]);
        $this->assertSoftDeleted('switch_extensions', ['extension_id' => $extension->getKey()]);
        $this->assertDatabaseHas('extension_lifecycle_operations', [
            'switch_extension_id' => $extension->getKey(),
            'operation' => 'delete',
            'status' => 'succeeded',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'extension.deleted',
            'outcome' => 'succeeded',
        ]);
    }

    public function test_deletion_requires_the_exact_extension_number_and_a_clear_preview(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'extension' => '1099',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        SwitchDevice::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'is_managed' => false,
        ]);
        $this->mock(SwitchExtensionProvisioningGateway::class)->shouldNotReceive('deleteUser');

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}", [
                'confirmation' => 'wrong',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmation');

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}", [
                'confirmation' => '1099',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('extension');
    }

    public function test_it_resumes_an_interrupted_deletion_without_repeating_completed_steps(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-resume',
            'extension' => '1088',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'switch_resource_id' => 'switch-callflow-resume',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'switch_resource_id' => 'switch-device-resume',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        SwitchVoicemailBox::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'switch_resource_id' => 'switch-vmbox-resume',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        $deviceAttempts = 0;
        $gateway = $this->mock(SwitchExtensionProvisioningGateway::class);
        $gateway->shouldReceive('deleteCallflow')->once()->withArgs(
            fn ($providedAccount, string $resourceId): bool => $providedAccount->is($account)
                && $resourceId === 'switch-callflow-resume',
        );
        $gateway->shouldReceive('deleteDevice')->twice()->withArgs(
            fn ($providedAccount, string $resourceId): bool => $providedAccount->is($account)
                && $resourceId === 'switch-device-resume',
        )
            ->andReturnUsing(function () use (&$deviceAttempts): void {
                $deviceAttempts++;

                if ($deviceAttempts === 1) {
                    throw new RuntimeException('Switch device delete unavailable.');
                }
            });
        $gateway->shouldReceive('deleteVoicemailBox')->once()->withArgs(
            fn ($providedAccount, string $resourceId): bool => $providedAccount->is($account)
                && $resourceId === 'switch-vmbox-resume',
        );
        $gateway->shouldReceive('deleteUser')->once()->withArgs(
            fn ($providedAccount, string $resourceId): bool => $providedAccount->is($account)
                && $resourceId === 'switch-user-resume',
        );

        $failed = $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}", [
                'confirmation' => '1088',
            ])
            ->assertStatus(502)
            ->assertJsonPath('code', 'extension_repair_required')
            ->assertJsonPath('repair_required', true);
        $operation = ExtensionLifecycleOperation::query()->sole();
        $this->assertSame($operation->id, $failed->json('operation_id'));
        $this->assertSame(["callflow:{$callflow->id}"], $operation->completed_steps);
        $this->assertSame("device:{$device->id}", $operation->failed_step);
        $this->assertDatabaseHas('switch_callflows', [
            'callflow_id' => $callflow->getKey(),
            'deleted_at' => null,
        ]);
        $callflow->delete(); // Simulate reconciliation observing the completed upstream step.

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}", [
                'confirmation' => '1088',
            ])
            ->assertNoContent();

        $this->assertSame(1, ExtensionLifecycleOperation::query()->count());
        $this->assertDatabaseHas('extension_lifecycle_operations', [
            'extension_lifecycle_operation_id' => $operation->getKey(),
            'status' => 'succeeded',
        ]);
    }

    public function test_it_rejects_a_second_confirmation_while_deletion_is_running(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'extension' => '1077',
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        ExtensionLifecycleOperation::query()->create([
            'switch_account_id' => $account->getKey(),
            'switch_extension_id' => $extension->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'operation' => 'delete',
            'status' => 'running',
            'completed_steps' => [],
        ]);
        $this->mock(SwitchExtensionProvisioningGateway::class)->shouldNotReceive('deleteCallflow');

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/extensions/{$extension->id}", [
                'confirmation' => '1077',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('extension');
    }

    /** @return array<string, mixed> */
    private function extensionPayload(): array
    {
        return [
            'first_name' => 'Alice',
            'last_name' => 'Operator',
            'extension' => '1001',
            'username' => 'alice.operator',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
            'require_password_update' => true,
            'clear_credentials' => false,
            'email' => 'alice@example.test',
            'timezone' => 'Asia/Manila',
            'is_enabled' => true,
            'language' => 'en-US',
            'presence_id' => 'alice@pbx.example.test',
            'call_waiting' => ['enabled' => false],
            'do_not_disturb' => ['enabled' => true],
            'contact_list' => ['exclude' => true],
            'caller_id_options' => ['outbound_privacy' => 'name'],
            'hotdesk' => [
                'enabled' => true,
                'id' => '1001',
                'keep_logged_in_elsewhere' => true,
                'require_pin' => true,
                'pin' => '2468',
                'clear_pin' => false,
            ],
            'voicemail' => [
                'enabled' => true,
                'notification_emails' => ['alice@example.test'],
                'transcribe' => false,
                'require_pin' => true,
                'pin' => '1234',
            ],
            'device' => [
                'enabled' => true,
                'name' => 'Alice desk phone',
                'device_type' => 'sip_device',
                'make' => null,
                'model' => null,
                'mac_address' => null,
                'sip_username' => 'alice-1001',
                'sip_password' => 'a-long-random-secret',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function updatePayload(): array
    {
        return [
            'first_name' => 'Alice',
            'last_name' => 'Support',
            'extension' => '1010',
            'username' => 'alice.support',
            'password' => null,
            'password_confirmation' => null,
            'require_password_update' => false,
            'clear_credentials' => false,
            'email' => 'alice@example.test',
            'timezone' => 'Asia/Manila',
            'is_enabled' => true,
            'language' => 'en-US',
            'presence_id' => 'alice@pbx.example.test',
            'call_waiting' => ['enabled' => false],
            'do_not_disturb' => ['enabled' => true],
            'contact_list' => ['exclude' => true],
            'caller_id_options' => ['outbound_privacy' => 'name'],
            'hotdesk' => [
                'enabled' => true,
                'id' => '1001',
                'keep_logged_in_elsewhere' => true,
                'require_pin' => true,
                'pin' => null,
                'clear_pin' => false,
            ],
            'voicemail' => [
                'enabled' => true,
                'notification_emails' => ['alice@example.test'],
                'transcribe' => true,
                'require_pin' => true,
                'pin' => null,
            ],
        ];
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
