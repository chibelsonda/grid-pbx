<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Enums\PivotResponseFormat;
use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\CallRouting\Services\PivotEndpointRegistry;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PivotEndpointRegistryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_enables_an_active_approved_profile_without_a_separate_runtime_flag(): void
    {
        $account = SwitchAccount::factory()->create();
        $profile = CallflowIntegrationProfile::factory()->for($account)->create();
        $registry = app(PivotEndpointRegistry::class);

        $this->assertTrue($registry->capability($account)['enabled']);
        $this->assertNull($registry->capability($account)['reason']);
        $this->assertSame($profile->id, $registry->publicEndpoints($account)[0]['id']);
    }

    #[Test]
    public function it_fails_closed_without_an_active_approved_profile(): void
    {
        $account = SwitchAccount::factory()->create();
        $registry = app(PivotEndpointRegistry::class);

        $this->assertFalse($registry->capability($account)['enabled']);
        $this->assertStringContainsString(
            'No active administrator-approved Pivot profile',
            $registry->capability($account)['reason'],
        );
        $this->assertSame([], $registry->publicEndpoints($account));

        $this->expectException(ValidationException::class);
        $registry->settingsForSwitch($account, [
            'endpoint_id' => '00000000-0000-4000-8000-000000000001',
            'method' => 'post',
            'req_format' => 'twiml',
            'skip_module' => false,
        ]);
    }

    #[Test]
    public function it_exposes_only_alias_metadata_and_resolves_private_switch_settings(): void
    {
        $account = SwitchAccount::factory()->create();
        $profile = CallflowIntegrationProfile::factory()->for($account)->create([
            'name' => 'Customer IVR',
            'settings' => [
                'voice_url' => 'https://voice.example.test/pivot',
                'cdr_url' => 'https://voice.example.test/cdr',
                'methods' => ['post'],
                'formats' => ['twiml'],
                'req_body_format' => 'json',
                'req_timeout_ms' => 4500,
                'debug' => false,
                'custom_request_headers' => ['X-Pivot-Key' => 'private-secret'],
            ],
        ]);

        $registry = app(PivotEndpointRegistry::class);

        $this->assertTrue($registry->capability($account)['enabled']);
        $this->assertSame([[
            'id' => $profile->id,
            'label' => 'Customer IVR',
            'methods' => ['post'],
            'formats' => ['twiml'],
        ]], $registry->publicEndpoints($account));

        $switch = $registry->settingsForSwitch($account, [
            'endpoint_id' => $profile->id,
            'method' => 'post',
            'req_format' => 'twiml',
            'skip_module' => false,
        ]);

        $this->assertSame('https://voice.example.test/pivot', $switch['voice_url']);
        $this->assertSame(['X-Pivot-Key' => 'private-secret'], $switch['custom_request_headers']);
        $this->assertFalse($switch['debug']);
        $this->assertArrayNotHasKey('endpoint_id', $switch);
        $this->assertSame([
            'supported_configuration' => true,
            'endpoint_id' => $profile->id,
            'endpoint_label' => 'Customer IVR',
            'method' => 'post',
            'req_format' => 'twiml',
            'skip_module' => false,
        ], $registry->publicSettings($account, $switch));

        $encrypted = DB::table('callflow_integration_profiles')
            ->where('id', $profile->id)
            ->value('settings');

        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString('voice.example.test', $encrypted);
        $this->assertStringNotContainsString('private-secret', $encrypted);
    }

    #[Test]
    public function it_keeps_multiple_profiles_selectable_and_matches_the_complete_node_contract(): void
    {
        $account = SwitchAccount::factory()->create();
        $getProfile = CallflowIntegrationProfile::factory()->for($account)->create([
            'name' => 'GET application',
            'settings' => [
                'voice_url' => 'https://voice.example.test/shared',
                'methods' => ['get'],
                'formats' => ['switch'],
                'req_body_format' => 'form',
                'req_timeout_ms' => 3000,
                'custom_request_headers' => [],
            ],
        ]);
        $postProfile = CallflowIntegrationProfile::factory()->for($account)->create([
            'name' => 'POST application',
            'settings' => [
                'voice_url' => 'https://voice.example.test/shared',
                'methods' => ['post'],
                'formats' => ['twiml'],
                'req_body_format' => 'json',
                'req_timeout_ms' => 4000,
                'custom_request_headers' => [],
            ],
        ]);
        $registry = app(PivotEndpointRegistry::class);

        $this->assertSame(
            [$getProfile->id, $postProfile->id],
            array_column($registry->publicEndpoints($account), 'id'),
        );
        $this->assertSame($postProfile->id, $registry->publicSettings($account, [
            'voice_url' => 'https://voice.example.test/shared',
            'method' => 'post',
            'req_format' => 'twiml',
        ])['endpoint_id']);
        $this->assertSame('https://voice.example.test/shared', $registry->settingsForSwitch($account, [
            'endpoint_id' => $postProfile->id,
            'method' => 'post',
            'req_format' => 'twiml',
            'skip_module' => false,
        ])['voice_url']);
    }

    #[Test]
    public function it_translates_the_public_switch_format_at_the_upstream_boundary(): void
    {
        $account = SwitchAccount::factory()->create();
        $profile = CallflowIntegrationProfile::factory()->for($account)->create([
            'settings' => [
                'voice_url' => 'https://voice.example.test/pivot',
                'methods' => ['get'],
                'formats' => [PivotResponseFormat::Switch->toUpstreamValue()],
                'req_body_format' => 'form',
                'req_timeout_ms' => 5000,
                'custom_request_headers' => [],
            ],
        ]);
        $registry = app(PivotEndpointRegistry::class);

        $this->assertSame(['switch'], $registry->publicEndpoints($account)[0]['formats']);

        $upstream = $registry->settingsForSwitch($account, [
            'endpoint_id' => $profile->id,
            'method' => 'get',
            'req_format' => 'switch',
            'skip_module' => false,
        ]);
        $this->assertSame(PivotResponseFormat::Switch->toUpstreamValue(), $upstream['req_format']);
        $this->assertSame('switch', $registry->publicSettings($account, $upstream)['req_format']);
    }

    #[Test]
    public function it_ignores_unsafe_endpoint_configuration(): void
    {
        $account = SwitchAccount::factory()->create();
        CallflowIntegrationProfile::factory()->for($account)->create([
            'name' => 'Unsafe',
            'settings' => [
                'voice_url' => 'https://127.0.0.1/private',
                'methods' => ['get'],
                'formats' => ['switch'],
                'req_body_format' => 'form',
                'req_timeout_ms' => 5000,
                'custom_request_headers' => [],
            ],
        ]);

        $registry = app(PivotEndpointRegistry::class);

        $this->assertFalse($registry->capability($account)['enabled']);
        $this->assertSame([], $registry->publicEndpoints($account));
    }

    #[Test]
    public function it_never_exposes_another_accounts_profiles(): void
    {
        $account = SwitchAccount::factory()->create();
        $otherAccount = SwitchAccount::factory()->create();
        CallflowIntegrationProfile::factory()->for($otherAccount)->create();

        $registry = app(PivotEndpointRegistry::class);

        $this->assertFalse($registry->capability($account)['enabled']);
        $this->assertSame([], $registry->publicEndpoints($account));
    }
}
