<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Contracts\DisaOperationalGuard;
use App\Domains\CallRouting\Dto\DisaOperationalReadiness;
use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\CallRouting\Services\DisaAccessPolicyRegistry;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DisaAccessPolicyRegistryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_fails_closed_without_an_active_approved_policy(): void
    {
        $account = SwitchAccount::factory()->create();
        $registry = app(DisaAccessPolicyRegistry::class);

        $this->assertFalse($registry->capability($account)['enabled']);
        $this->assertStringContainsString(
            'No active administrator-approved DISA access policy',
            $registry->capability($account)['reason'],
        );
        $this->assertSame([], $registry->publicPolicies($account));

        $this->expectException(ValidationException::class);
        $registry->settingsForSwitch($account, [
            'access_policy_id' => '00000000-0000-4000-8000-000000000001',
            'skip_module' => false,
        ]);
    }

    #[Test]
    public function it_exposes_only_public_policy_metadata_and_resolves_the_private_pin(): void
    {
        $account = SwitchAccount::factory()->create();
        $this->enableOperationalGuard();
        $profile = CallflowIntegrationProfile::factory()->for($account)->disa()->create([
            'name' => 'After-hours access',
        ]);
        $registry = app(DisaAccessPolicyRegistry::class);

        $this->assertTrue($registry->capability($account)['enabled']);
        $this->assertSame([[
            'id' => $profile->id,
            'label' => 'After-hours access',
            'retries' => 2,
            'interdigit_ms' => 3000,
            'max_digits' => 15,
            'preconnect_audio' => 'dialtone',
        ]], $registry->publicPolicies($account));

        $switch = $registry->settingsForSwitch($account, [
            'access_policy_id' => $profile->id,
            'skip_module' => false,
        ]);

        $this->assertSame('82736491', $switch['pin']);
        $this->assertFalse($switch['use_account_caller_id']);
        $this->assertTrue($switch['enforce_call_restriction']);
        $this->assertArrayNotHasKey('access_policy_id', $switch);
        $this->assertSame([
            'supported_configuration' => true,
            'access_policy_id' => $profile->id,
            'access_policy_label' => 'After-hours access',
            'skip_module' => false,
        ], $registry->publicSettings($account, $switch));

        $encrypted = DB::table('callflow_integration_profiles')
            ->where('id', $profile->id)
            ->value('settings');

        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString('82736491', $encrypted);
    }

    #[Test]
    public function it_keeps_an_active_policy_locked_until_the_ingress_guard_is_ready(): void
    {
        $account = SwitchAccount::factory()->create();
        CallflowIntegrationProfile::factory()->for($account)->disa()->create();
        $registry = app(DisaAccessPolicyRegistry::class);

        $capability = $registry->capability($account);

        $this->assertFalse($capability['enabled']);
        $this->assertStringContainsString('carrier/SBC operational guard', $capability['reason']);
        $this->assertFalse($registry->operationalStatus($account)['ready']);
        $this->assertTrue($registry->operationalStatus($account)['emergency_stop_active']);

        $this->expectException(ValidationException::class);
        $registry->settingsForSwitch($account, [
            'access_policy_id' => CallflowIntegrationProfile::query()->value('id'),
            'skip_module' => false,
        ]);
    }

    #[Test]
    public function it_ignores_unsafe_or_cross_account_policies(): void
    {
        $account = SwitchAccount::factory()->create();
        $otherAccount = SwitchAccount::factory()->create();
        CallflowIntegrationProfile::factory()->for($account)->disa()->inactive()->create();
        CallflowIntegrationProfile::factory()->for($account)->disa()->create([
            'settings' => [
                'pin' => '1234',
                'retries' => 9,
                'interdigit_ms' => 3000,
                'max_digits' => 15,
                'preconnect_audio' => 'dialtone',
            ],
        ]);
        CallflowIntegrationProfile::factory()->for($otherAccount)->disa()->create();

        $registry = app(DisaAccessPolicyRegistry::class);

        $this->assertFalse($registry->capability($account)['enabled']);
        $this->assertSame([], $registry->publicPolicies($account));
    }

    private function enableOperationalGuard(): void
    {
        $this->app->instance(DisaOperationalGuard::class, new class implements DisaOperationalGuard
        {
            public function inspect(SwitchAccount $account): DisaOperationalReadiness
            {
                return DisaOperationalReadiness::available('test-sbc');
            }
        });
    }
}
