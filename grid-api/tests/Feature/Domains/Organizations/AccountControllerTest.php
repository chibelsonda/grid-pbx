<?php

namespace Tests\Feature\Domains\Organizations;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
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
            'switch_json' => [
                'call_recording' => [
                    'account' => ['any' => ['offnet' => ['url' => 'https://storage.example.test/recordings']]],
                ],
                'dial_plan' => [
                    '^([2-9][0-9]{6})$' => ['future_option' => true],
                ],
                'formatters' => [
                    'request' => ['future_option' => 'keep', 'regex' => '^sip:(.*)$'],
                ],
                'preflow' => ['always' => 'orphan-callflow'],
                'metaflows' => [
                    'binding_digit' => '*',
                    'digit_timeout' => 1500,
                    'listen_on' => 'both',
                    'numbers' => [
                        '3' => ['module' => 'hangup', 'data' => [], 'children' => []],
                        '9' => [
                            'module' => 'future_module',
                            'data' => ['future_id' => 'private-switch-value'],
                            'children' => [],
                        ],
                    ],
                    'future_option' => true,
                ],
            ],
        ]);
        $preflow = SwitchCallflow::factory()->for($account, 'switchAccount')->create([
            'switch_resource_id' => 'switch-callflow-1',
            'name' => 'Main inbound route',
            'numbers' => ['2000'],
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
                && $data['call_restriction']['international']['action'] === 'deny'
                && $data['call_recording']['account']['any']['offnet']['enabled'] === true
                && $data['call_recording']['account']['any']['offnet']['url'] === 'https://storage.example.test/recordings'
                && $data['dial_plan']['rules'][0]['preserved_options']['future_option'] === true
                && $data['formatters'][0]['preserved_options']['future_option'] === 'keep'
                && $data['preflow']['always'] === 'switch-callflow-1'
                && $data['metaflows']['preserved_options']['future_option'] === true
                && ! array_key_exists('3', $data['metaflows']['preserved_options']['numbers'])
                && $data['metaflows']['preserved_options']['numbers']['4']['module'] === 'callflow'
                && $data['metaflows']['preserved_options']['numbers']['4']['data']['id'] === 'switch-callflow-1'
                && $data['metaflows']['preserved_options']['numbers']['9']['module'] === 'future_module'
                && ! array_key_exists('phone_number_id', $data['caller_id']['external']))
            ->andReturn($this->accountSnapshot([
                'password' => 'must-not-be-stored',
                'call_restriction' => ['international' => ['action' => 'deny']],
                'call_recording' => [
                    'account' => [
                        'any' => [
                            'offnet' => [
                                'enabled' => true,
                                'format' => 'wav',
                                'url' => 'https://storage.example.test/recordings',
                            ],
                        ],
                    ],
                ],
                'dial_plan' => [
                    'system' => ['north_america'],
                    '^([2-9][0-9]{6})$' => [
                        'future_option' => true,
                        'description' => 'Local calls',
                        'prefix' => '+1555',
                    ],
                ],
                'formatters' => [
                    'request' => [
                        'future_option' => 'keep',
                        'direction' => 'both',
                        'regex' => '^sip:(.*)$',
                    ],
                ],
                'preflow' => ['always' => 'switch-callflow-1'],
                'metaflows' => [
                    'binding_digit' => '#',
                    'digit_timeout' => 2500,
                    'listen_on' => 'self',
                    'numbers' => [
                        '4' => [
                            'module' => 'callflow',
                            'data' => ['id' => 'switch-callflow-1'],
                            'children' => [],
                        ],
                        '9' => [
                            'module' => 'future_module',
                            'data' => ['future_id' => 'private-switch-value'],
                            'children' => [],
                        ],
                    ],
                    'future_option' => true,
                ],
            ]));

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
            'call_restriction' => ['international' => ['action' => 'deny']],
            'call_recording' => [
                'account' => [
                    'any' => [
                        'offnet' => [
                            'enabled' => true,
                            'format' => 'wav',
                            'record_min_sec' => 5,
                            'record_on_answer' => true,
                            'record_on_bridge' => false,
                            'record_sample_rate' => 16000,
                            'time_limit' => 3600,
                        ],
                    ],
                ],
            ],
            'dial_plan' => [
                'system' => ['north_america'],
                'rules' => [[
                    'pattern' => '^([2-9][0-9]{6})$',
                    'description' => 'Local calls',
                    'prefix' => '+1555',
                    'suffix' => null,
                ]],
            ],
            'formatters' => [[
                'field' => 'request',
                'direction' => 'both',
                'match_invite_format' => false,
                'prefix' => null,
                'regex' => '^sip:(.*)$',
                'strip' => false,
                'suffix' => null,
                'value' => null,
            ]],
            'preflow' => [
                'callflow_id' => $preflow->id,
                'preserve_callflow' => false,
            ],
            'metaflows' => [
                'binding_digit' => '#',
                'digit_timeout' => 2500,
                'listen_on' => 'self',
                'actions' => [[
                    'trigger_type' => 'number',
                    'trigger' => '4',
                    'module' => 'callflow',
                    'data' => ['callflow_id' => $preflow->id],
                    'children' => [],
                ]],
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
            ->assertJsonPath('data.configuration.call_restriction.international.action', 'deny')
            ->assertJsonPath('data.configuration.call_recording.account.any.offnet.enabled', true)
            ->assertJsonPath('data.configuration.dial_plan.rules.0.pattern', '^([2-9][0-9]{6})$')
            ->assertJsonPath('data.configuration.formatters.0.field', 'request')
            ->assertJsonPath('data.configuration.preflow.callflow_id', $preflow->id)
            ->assertJsonPath('data.configuration.metaflows.binding_digit', '#')
            ->assertJsonPath('data.configuration.metaflows.number_flow_count', 2)
            ->assertJsonPath('data.configuration.metaflows.actions.0.trigger', '4')
            ->assertJsonPath('data.configuration.metaflows.actions.0.module', 'callflow')
            ->assertJsonPath('data.configuration.metaflows.actions.0.data.callflow_id', $preflow->id)
            ->assertJsonPath('data.configuration.metaflows.locked_action_count', 1)
            ->assertJsonPath('data.options.caller_id_numbers.0.id', $externalNumber->id)
            ->assertJsonMissingPath('data.configuration.dial_plan.rules.0.future_option')
            ->assertJsonMissingPath('data.configuration.formatters.0.future_option')
            ->assertJsonMissingPath('data.configuration.metaflows.numbers')
            ->assertJsonMissingPath('data.configuration.metaflows.future_option')
            ->assertJsonMissingPath('data.configuration.call_recording.account.any.offnet.url')
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

    public function test_it_returns_connected_switch_restriction_classifiers_for_a_member_account(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create();
        $callflow = SwitchCallflow::factory()->for($account, 'switchAccount')->create([
            'switch_resource_id' => 'switch-callflow-1',
            'name' => 'Main inbound route',
            'numbers' => ['2000'],
        ]);
        $media = SwitchMedia::factory()->for($account)->create(['name' => 'Welcome greeting']);
        $device = SwitchDevice::factory()->for($account)->create(['name' => 'Reception phone']);
        $extension = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Reception',
            'extension' => '2000',
        ]);
        $this->mock(SwitchAccountGateway::class)
            ->shouldReceive('restrictionClassifiers')
            ->once()
            ->withArgs(fn (SwitchAccount $received): bool => $received->is($account))
            ->andReturn([
                ['key' => 'international', 'label' => 'International', 'emergency' => false],
                ['key' => 'emergency', 'label' => 'Emergency', 'emergency' => true],
            ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/settings-options")
            ->assertOk()
            ->assertJsonPath('data.restrictions.0.key', 'international')
            ->assertJsonPath('data.restrictions.1.emergency', true)
            ->assertJsonPath('data.callflows.0.id', $callflow->id)
            ->assertJsonPath('data.callflows.0.name', 'Main inbound route')
            ->assertJsonPath('data.callflows.0.description', '2000')
            ->assertJsonPath('data.metaflow_resources.media.0.id', $media->id)
            ->assertJsonPath('data.metaflow_resources.callflows.0.id', $callflow->id)
            ->assertJsonPath('data.metaflow_resources.devices.0.id', $device->id)
            ->assertJsonPath('data.metaflow_resources.extensions.0.id', $extension->id)
            ->assertJsonMissingPath('data.callflows.0.switch_resource_id');
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

    public function test_it_explicitly_preserves_or_clears_an_unresolved_account_preflow(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create([
            'switch_account_id' => 'switch-account-1',
            'switch_json' => ['preflow' => ['always' => 'orphan-callflow']],
        ]);
        $calls = 0;
        $this->mock(SwitchAccountGateway::class)
            ->shouldReceive('updateSettings')
            ->twice()
            ->withArgs(function (SwitchAccount $received, array $data) use ($account, &$calls): bool {
                $calls++;

                return $received->is($account)
                    && ($calls === 1
                        ? $data['preflow']['always'] === 'orphan-callflow'
                        : $data['preflow']['always'] === null);
            })
            ->andReturn(
                $this->accountSnapshot(['preflow' => ['always' => 'orphan-callflow']]),
                $this->accountSnapshot(['preflow' => []]),
            );
        $base = [
            'name' => 'Grid Support',
            'call_waiting_enabled' => true,
            'do_not_disturb_enabled' => false,
            'outbound_privacy' => 'none',
            'show_rate' => false,
            'caller_id' => [
                'internal' => ['name' => null, 'number' => null],
                'external' => ['name' => null, 'phone_number_id' => null, 'preserve_number' => false],
                'emergency' => ['name' => null, 'phone_number_id' => null, 'preserve_number' => false],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}", $base + [
                'preflow' => ['callflow_id' => null, 'preserve_callflow' => true],
            ])
            ->assertOk()
            ->assertJsonPath('data.configuration.preflow.unresolved', true)
            ->assertJsonPath('data.configuration.preflow.callflow_id', null)
            ->assertJsonMissingPath('data.configuration.preflow.switch_resource_id');

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}", $base + [
                'preflow' => ['callflow_id' => null, 'preserve_callflow' => false],
            ])
            ->assertOk()
            ->assertJsonPath('data.configuration.preflow.unresolved', false)
            ->assertJsonPath('data.configuration.preflow.callflow_id', null);
    }

    public function test_it_rejects_unsafe_or_duplicate_account_routing_expressions(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_administrator']);
        $account = SwitchAccount::factory()->for($organization)->create();
        $foreignCallflow = SwitchCallflow::factory()->create();
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
            'dial_plan' => [
                'system' => [],
                'rules' => [
                    ['pattern' => '^([0-9]+)$', 'description' => null, 'prefix' => null, 'suffix' => null],
                    ['pattern' => '^([0-9]+)$', 'description' => null, 'prefix' => null, 'suffix' => null],
                ],
            ],
            'formatters' => [[
                'field' => 'request',
                'direction' => 'both',
                'match_invite_format' => false,
                'prefix' => null,
                'regex' => '(?R)',
                'strip' => false,
                'suffix' => null,
                'value' => null,
            ]],
            'preflow' => [
                'callflow_id' => $foreignCallflow->id,
                'preserve_callflow' => false,
            ],
            'metaflows' => [
                'binding_digit' => 'A',
                'digit_timeout' => 60001,
                'listen_on' => 'other',
                'actions' => [[
                    'trigger_type' => 'pattern',
                    'trigger' => '(?R)',
                    'module' => 'callflow',
                    'data' => ['callflow_id' => $foreignCallflow->id],
                    'children' => [],
                ]],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'dial_plan.rules',
                'formatters.0.regex',
                'preflow.callflow_id',
                'metaflows.binding_digit',
                'metaflows.digit_timeout',
                'metaflows.listen_on',
                'metaflows.actions.0.trigger',
                'metaflows.actions.0.data.callflow_id',
            ]);
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
