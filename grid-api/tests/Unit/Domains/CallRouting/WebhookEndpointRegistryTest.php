<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Enums\CallflowIntegrationType;
use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\CallRouting\Services\WebhookEndpointRegistry;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookEndpointRegistryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_enables_an_active_approved_profile_without_a_separate_runtime_flag(): void
    {
        $account = SwitchAccount::factory()->create();
        CallflowIntegrationProfile::factory()->for($account)->create([
            'integration_type' => CallflowIntegrationType::Webhook,
            'settings' => [
                'uri' => 'https://events.example.test/calls',
                'methods' => ['post'],
                'max_retries' => 3,
            ],
        ]);
        $registry = app(WebhookEndpointRegistry::class);

        $this->assertTrue($registry->capability($account)['enabled']);
        $this->assertNull($registry->capability($account)['reason']);
        $this->assertCount(1, $registry->publicEndpoints($account));
    }

    #[Test]
    public function it_fails_closed_without_an_active_approved_endpoint(): void
    {
        $account = SwitchAccount::factory()->create();
        $registry = app(WebhookEndpointRegistry::class);

        $this->assertFalse($registry->capability($account)['enabled']);
        $this->assertSame([], $registry->publicEndpoints($account));

        $this->expectException(ValidationException::class);
        $registry->settingsForSwitch($account, [
            'endpoint_id' => '00000000-0000-4000-8000-000000000001',
            'http_verb' => 'post',
            'retries' => 1,
            'custom_data' => [],
            'skip_module' => false,
        ]);
    }

    #[Test]
    public function it_exposes_only_alias_metadata_and_resolves_private_switch_settings(): void
    {
        $account = SwitchAccount::factory()->create();
        $profile = CallflowIntegrationProfile::factory()->for($account)->create([
            'integration_type' => CallflowIntegrationType::Webhook,
            'name' => 'CRM event receiver',
            'settings' => [
                'uri' => 'https://events.example.test/calls',
                'methods' => ['post'],
                'max_retries' => 3,
            ],
        ]);
        $registry = app(WebhookEndpointRegistry::class);

        $this->assertSame([[
            'id' => $profile->id,
            'label' => 'CRM event receiver',
            'methods' => ['post'],
            'max_retries' => 3,
        ]], $registry->publicEndpoints($account));

        $switch = $registry->settingsForSwitch($account, [
            'endpoint_id' => $profile->id,
            'http_verb' => 'post',
            'retries' => 2,
            'custom_data' => ['source' => 'support', 'priority' => 4],
            'skip_module' => false,
        ]);

        $this->assertSame('https://events.example.test/calls', $switch['uri']);
        $this->assertArrayNotHasKey('endpoint_id', $switch);
        $this->assertSame([
            'supported_configuration' => true,
            'endpoint_id' => $profile->id,
            'endpoint_label' => 'CRM event receiver',
            'http_verb' => 'post',
            'retries' => 2,
            'custom_data' => ['source' => 'support', 'priority' => 4],
            'skip_module' => false,
        ], $registry->publicSettings($account, $switch));

        $encrypted = DB::table('callflow_integration_profiles')->where('id', $profile->id)->value('settings');
        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString('events.example.test', $encrypted);
    }

    #[Test]
    public function it_keeps_multiple_profiles_selectable_and_matches_the_complete_node_contract(): void
    {
        $account = SwitchAccount::factory()->create();
        $getProfile = CallflowIntegrationProfile::factory()->for($account)->create([
            'integration_type' => CallflowIntegrationType::Webhook,
            'name' => 'GET receiver',
            'settings' => [
                'uri' => 'https://events.example.test/shared',
                'methods' => ['get'],
                'max_retries' => 1,
            ],
        ]);
        $postProfile = CallflowIntegrationProfile::factory()->for($account)->create([
            'integration_type' => CallflowIntegrationType::Webhook,
            'name' => 'POST receiver',
            'settings' => [
                'uri' => 'https://events.example.test/shared',
                'methods' => ['post'],
                'max_retries' => 4,
            ],
        ]);
        $registry = app(WebhookEndpointRegistry::class);

        $this->assertSame(
            [$getProfile->id, $postProfile->id],
            array_column($registry->publicEndpoints($account), 'id'),
        );
        $this->assertSame($postProfile->id, $registry->publicSettings($account, [
            'uri' => 'https://events.example.test/shared',
            'http_verb' => 'post',
            'retries' => 4,
            'custom_data' => [],
        ])['endpoint_id']);
        $this->assertSame('https://events.example.test/shared', $registry->settingsForSwitch($account, [
            'endpoint_id' => $postProfile->id,
            'http_verb' => 'post',
            'retries' => 4,
            'custom_data' => [],
            'skip_module' => false,
        ])['uri']);
    }

    #[Test]
    public function it_rejects_methods_retries_and_custom_data_outside_the_profile_policy(): void
    {
        $account = SwitchAccount::factory()->create();
        $profile = CallflowIntegrationProfile::factory()->for($account)->create([
            'integration_type' => CallflowIntegrationType::Webhook,
            'settings' => [
                'uri' => 'https://events.example.test/calls',
                'methods' => ['post'],
                'max_retries' => 2,
            ],
        ]);
        $registry = app(WebhookEndpointRegistry::class);

        foreach ([
            ['http_verb' => 'get', 'retries' => 1, 'custom_data' => []],
            ['http_verb' => 'post', 'retries' => 3, 'custom_data' => []],
            ['http_verb' => 'post', 'retries' => 1, 'custom_data' => ['bad key' => 'value']],
        ] as $settings) {
            try {
                $registry->settingsForSwitch($account, [
                    'endpoint_id' => $profile->id,
                    ...$settings,
                    'skip_module' => false,
                ]);
                $this->fail('Expected the Webhook node settings to be rejected.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_rejects_unknown_profile_settings_instead_of_persisting_them(): void
    {
        $registry = app(WebhookEndpointRegistry::class);

        $this->expectException(ValidationException::class);
        $registry->validatedProfileSettings([
            'uri' => 'https://events.example.test/calls',
            'methods' => ['post'],
            'max_retries' => 3,
            'authorization' => 'private-secret',
        ]);
    }
}
