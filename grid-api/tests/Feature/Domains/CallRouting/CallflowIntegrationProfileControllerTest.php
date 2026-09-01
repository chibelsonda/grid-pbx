<?php

namespace Tests\Feature\Domains\CallRouting;

use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CallflowIntegrationProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_account_administrator_can_manage_an_encrypted_pivot_profile_without_exposing_secrets(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountAdministrator);
        $url = "/api/v1/accounts/{$account->id}/callflow-integration-profiles";
        $payload = [
            'integration_type' => 'pivot',
            'name' => 'Customer IVR',
            'is_active' => true,
            'settings' => [
                'voice_url' => 'https://voice.example.test/pivot',
                'cdr_url' => 'https://voice.example.test/cdr',
                'methods' => ['post'],
                'formats' => ['twiml'],
                'req_body_format' => 'json',
                'req_timeout_ms' => 4500,
                'custom_request_headers' => ['X-Pivot-Key' => 'private-secret'],
            ],
        ];

        $response = $this->actingAs($user)->postJson($url, $payload)
            ->assertCreated()
            ->assertJsonPath('data.integration_type', 'pivot')
            ->assertJsonPath('data.name', 'Customer IVR')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.configuration.methods.0', 'post')
            ->assertJsonPath('data.configuration.formats.0', 'twiml')
            ->assertJsonPath('data.configuration.has_cdr_callback', true)
            ->assertJsonPath('data.configuration.has_custom_headers', true)
            ->assertJsonMissing(['https://voice.example.test/pivot', 'private-secret']);
        $profileId = $response->json('data.id');

        $this->actingAs($user)->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $profileId)
            ->assertJsonMissing(['https://voice.example.test/pivot', 'private-secret']);

        $encrypted = DB::table('callflow_integration_profiles')->where('id', $profileId)->value('settings');
        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString('voice.example.test', $encrypted);
        $this->assertStringNotContainsString('private-secret', $encrypted);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'callflow.integration_profile_created',
            'resource_id' => $profileId,
            'outcome' => 'succeeded',
        ]);
    }

    public function test_account_administrator_can_disable_and_delete_a_profile_without_resending_secrets(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountAdministrator);
        $profile = CallflowIntegrationProfile::factory()->for($account)->create();
        $url = "/api/v1/accounts/{$account->id}/callflow-integration-profiles/{$profile->id}";

        $this->actingAs($user)->putJson($url, [
            'name' => 'Paused IVR',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Paused IVR')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('callflow_integration_profiles', [
            'id' => $profile->id,
            'name' => 'Paused IVR',
            'is_active' => false,
            'updated_by_user_id' => $user->getKey(),
        ]);

        $this->actingAs($user)->deleteJson($url)->assertNoContent();

        $this->assertSoftDeleted($profile);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'callflow.integration_profile_deleted',
            'resource_id' => $profile->id,
        ]);
    }

    public function test_account_administrator_can_create_an_encrypted_webhook_profile(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountAdministrator);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflow-integration-profiles", [
                'integration_type' => 'webhook',
                'name' => 'CRM events',
                'is_active' => true,
                'settings' => [
                    'uri' => 'https://events.example.test/calls',
                    'methods' => ['post'],
                    'max_retries' => 3,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.integration_type', 'webhook')
            ->assertJsonPath('data.configuration.methods.0', 'post')
            ->assertJsonPath('data.configuration.max_retries', 3)
            ->assertJsonMissing(['https://events.example.test/calls']);

        $encrypted = DB::table('callflow_integration_profiles')
            ->where('id', $response->json('data.id'))
            ->value('settings');

        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString('events.example.test', $encrypted);
    }

    public function test_account_administrator_can_create_an_encrypted_disa_policy_without_exposing_its_pin(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountAdministrator);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflow-integration-profiles", [
                'integration_type' => 'disa',
                'name' => 'After-hours access',
                'is_active' => true,
                'settings' => [
                    'pin' => '82736491',
                    'retries' => 2,
                    'interdigit_ms' => 3000,
                    'max_digits' => 15,
                    'preconnect_audio' => 'dialtone',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.integration_type', 'disa')
            ->assertJsonPath('data.configuration.pin_configured', true)
            ->assertJsonPath('data.configuration.retries', 2)
            ->assertJsonPath('data.configuration.enforce_call_restriction', true)
            ->assertJsonPath('data.configuration.use_account_caller_id', false)
            ->assertJsonMissing(['82736491']);

        $encrypted = DB::table('callflow_integration_profiles')
            ->where('id', $response->json('data.id'))
            ->value('settings');

        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString('82736491', $encrypted);
    }

    public function test_disa_policy_rejects_weak_credentials_and_unbounded_native_settings(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountAdministrator);
        $url = "/api/v1/accounts/{$account->id}/callflow-integration-profiles";

        $this->actingAs($user)->postJson($url, [
            'integration_type' => 'disa',
            'name' => 'Unsafe access',
            'is_active' => true,
            'settings' => [
                'pin' => '1234',
                'retries' => 99,
                'interdigit_ms' => 10000,
                'max_digits' => 32,
                'preconnect_audio' => 'custom',
                'enforce_call_restriction' => false,
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'settings',
                'settings.pin',
                'settings.retries',
                'settings.interdigit_ms',
                'settings.max_digits',
                'settings.preconnect_audio',
            ]);
    }

    public function test_non_administrator_cannot_manage_profiles(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountOperator);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/callflow-integration-profiles")
            ->assertForbidden();
    }

    public function test_account_administrator_can_authorize_carrier_scopes_without_public_switch_ids(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountAdministrator);
        $url = "/api/v1/accounts/{$account->id}/callflow-integration-profiles";

        $this->actingAs($user)->postJson($url, [
            'integration_type' => 'global_carrier',
            'name' => 'System carrier pool',
            'is_active' => true,
            'settings' => [],
        ])->assertCreated()
            ->assertJsonPath('data.configuration.route_scope', 'global');

        $this->actingAs($user)->postJson($url, [
            'integration_type' => 'account_carrier',
            'name' => 'Owned resources',
            'is_active' => true,
            'settings' => ['scope' => 'account'],
        ])->assertCreated()
            ->assertJsonPath('data.configuration.route_scope', 'account')
            ->assertJsonMissing([$account->switch_account_id]);

        $this->actingAs($user)->getJson($url)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing([$account->switch_account_id]);
    }

    public function test_carrier_profiles_reject_arbitrary_switch_routing_fields(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountAdministrator);
        $url = "/api/v1/accounts/{$account->id}/callflow-integration-profiles";

        $this->actingAs($user)->postJson($url, [
            'integration_type' => 'global_carrier',
            'name' => 'Unsafe global route',
            'is_active' => true,
            'settings' => ['hunt_account_id' => 'raw-switch-id'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('settings');

        $this->actingAs($user)->postJson($url, [
            'integration_type' => 'account_carrier',
            'name' => 'Unsafe account route',
            'is_active' => true,
            'settings' => [
                'scope' => 'account',
                'hunt_account_id' => 'raw-switch-id',
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('settings');
    }

    public function test_profile_lookup_is_account_scoped(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountAdministrator);
        $foreignProfile = CallflowIntegrationProfile::factory()->create();

        $this->actingAs($user)
            ->putJson(
                "/api/v1/accounts/{$account->id}/callflow-integration-profiles/{$foreignProfile->id}",
                ['is_active' => false],
            )
            ->assertNotFound();
    }

    public function test_it_rejects_unsafe_private_pivot_urls(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountAdministrator);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflow-integration-profiles", [
                'integration_type' => 'pivot',
                'name' => 'Unsafe callback',
                'is_active' => true,
                'settings' => [
                    'voice_url' => 'https://127.0.0.1/private',
                    'cdr_url' => null,
                    'methods' => ['get'],
                    'formats' => ['kazoo'],
                    'req_body_format' => 'form',
                    'req_timeout_ms' => 5000,
                    'custom_request_headers' => [],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings');

        $this->assertDatabaseCount('callflow_integration_profiles', 0);
    }

    public function test_it_rejects_browser_controlled_pivot_debug_persistence(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountAdministrator);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/callflow-integration-profiles", [
                'integration_type' => 'pivot',
                'name' => 'Debug callback',
                'is_active' => true,
                'settings' => [
                    'voice_url' => 'https://voice.example.com/pivot',
                    'cdr_url' => null,
                    'methods' => ['post'],
                    'formats' => ['kazoo'],
                    'req_body_format' => 'json',
                    'req_timeout_ms' => 5000,
                    'debug' => true,
                    'custom_request_headers' => [],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings');

        $this->assertDatabaseCount('callflow_integration_profiles', 0);
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(OrganizationRole $role): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);
        $account = SwitchAccount::factory()->for($organization)->create();

        return [$user, $account];
    }
}
