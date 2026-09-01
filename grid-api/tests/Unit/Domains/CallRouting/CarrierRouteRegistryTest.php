<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\CallRouting\Services\CarrierRouteRegistry;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CarrierRouteRegistryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_resolves_global_and_current_account_routes_without_private_ids(): void
    {
        $account = SwitchAccount::factory()->create();
        $global = CallflowIntegrationProfile::factory()->for($account)->globalCarrier()->create([
            'name' => 'System carriers',
        ]);
        $owned = CallflowIntegrationProfile::factory()->for($account)->accountCarrier()->create([
            'name' => 'Owned resources',
        ]);
        $registry = app(CarrierRouteRegistry::class);

        $this->assertSame([
            'skip_module' => false,
        ], $registry->settingsForSwitch($account, 'offnet', [
            'route_profile_id' => $global->id,
            'skip_module' => false,
        ]));
        $this->assertSame([
            'skip_module' => true,
        ], $registry->settingsForSwitch($account, 'resources', [
            'route_profile_id' => $owned->id,
            'skip_module' => true,
        ]));

        $routes = $registry->publicRoutes($account);
        $this->assertCount(2, $routes);
        $this->assertStringNotContainsString($account->switch_account_id, json_encode($routes));
    }

    public function test_it_resolves_only_a_projected_reseller_ancestor_server_side(): void
    {
        $organization = Organization::factory()->create();
        $reseller = SwitchAccount::factory()->for($organization)->create([
            'is_reseller' => true,
            'switch_account_id' => 'private-reseller-switch-id',
        ]);
        $account = SwitchAccount::factory()->for($organization)->create([
            'parent_account_id' => $reseller->getKey(),
            'parent_switch_account_id' => $reseller->switch_account_id,
        ]);
        $profile = CallflowIntegrationProfile::factory()
            ->for($account)
            ->accountCarrier('reseller')
            ->create();
        $registry = app(CarrierRouteRegistry::class);

        $switchSettings = $registry->settingsForSwitch($account, 'resources', [
            'route_profile_id' => $profile->id,
            'skip_module' => false,
        ]);

        $this->assertSame('private-reseller-switch-id', $switchSettings['hunt_account_id']);
        $public = $registry->publicSettings($account, 'resources', $switchSettings);
        $this->assertTrue($public['supported_configuration']);
        $this->assertSame($profile->id, $public['route_profile_id']);
        $this->assertArrayNotHasKey('hunt_account_id', $public);
        $this->assertStringNotContainsString('private-reseller-switch-id', json_encode($public));
    }

    public function test_unprojected_reseller_scope_is_not_an_available_capability(): void
    {
        $account = SwitchAccount::factory()->create([
            'parent_account_id' => null,
            'parent_switch_account_id' => 'unprojected-reseller-id',
        ]);
        $profile = CallflowIntegrationProfile::factory()
            ->for($account)
            ->accountCarrier('reseller')
            ->create();
        $registry = app(CarrierRouteRegistry::class);

        $this->assertFalse($registry->capability($account, 'resources')['enabled']);

        $this->expectException(ValidationException::class);
        $registry->settingsForSwitch($account, 'resources', [
            'route_profile_id' => $profile->id,
            'skip_module' => false,
        ]);
    }
}
