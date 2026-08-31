<?php

namespace Tests\Feature\Domains\Dashboard;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Dashboard\Contracts\CallGeographyProvider;
use App\Domains\Dashboard\Dto\CallGeographyLocation;
use App\Domains\Dashboard\Models\CallGeographyAggregate;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CallGeographyControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reports_unavailable_without_an_approved_enrichment_source(): void
    {
        [$user, $account] = $this->accessibleAccount();
        config()->set('dashboard.call_geography.enabled', false);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/call-geography?range=7d")
            ->assertOk()
            ->assertJsonPath('data.status', 'unavailable')
            ->assertJsonPath('data.capability.available', false)
            ->assertJsonPath('data.capability.source', null)
            ->assertJsonPath('data.coverage.total_calls', 0)
            ->assertJsonCount(0, 'data.locations')
            ->assertJsonMissingPath('data.switch_account_id');
    }

    public function test_reports_unavailable_when_config_does_not_match_an_available_provider(): void
    {
        [$user, $account] = $this->accessibleAccount();
        config()->set('dashboard.call_geography.enabled', true);
        config()->set('dashboard.call_geography.source', 'configured-but-unavailable');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/call-geography?range=7d")
            ->assertOk()
            ->assertJsonPath('data.status', 'unavailable')
            ->assertJsonPath('data.capability.available', false)
            ->assertJsonPath('data.capability.source', null)
            ->assertJsonCount(0, 'data.locations');
    }

    public function test_returns_bounded_aggregate_numbering_plan_geography_and_coverage(): void
    {
        $this->travelTo('2026-08-31 12:00:00');
        [$user, $account] = $this->accessibleAccount(['timezone' => 'America/Los_Angeles']);
        $otherAccount = SwitchAccount::factory()->create();
        config()->set('dashboard.call_geography.enabled', true);
        config()->set('dashboard.call_geography.source', 'approved-test-source');
        $this->app->instance(CallGeographyProvider::class, new AvailableCallGeographyProvider);

        SwitchCallDetailRecord::factory()->for($account)->count(5)->create([
            'started_at' => '2026-08-31 08:00:00',
        ]);
        SwitchCallDetailRecord::factory()->for($account)->create([
            'started_at' => '2026-08-20 08:00:00',
        ]);
        SwitchCallDetailRecord::factory()->for($account)->create([
            'direction' => 'internal',
            'started_at' => '2026-08-31 08:00:00',
        ]);

        $this->aggregate($account, [
            'bucket_started_at' => '2026-08-30 08:00:00',
            'location_key' => 'us-wa-seattle',
            'locality' => 'Seattle',
            'region_code' => 'WA',
            'latitude' => 47.6062,
            'longitude' => -122.3321,
            'inbound_count' => 2,
            'outbound_count' => 0,
        ]);
        $this->aggregate($account, [
            'bucket_started_at' => '2026-08-31 08:00:00',
            'location_key' => 'us-wa-seattle',
            'locality' => 'Seattle',
            'region_code' => 'WA',
            'latitude' => 47.6062,
            'longitude' => -122.3321,
            'inbound_count' => 0,
            'outbound_count' => 1,
        ]);
        $this->aggregate($account, [
            'bucket_started_at' => '2026-08-31 08:00:00',
            'location_key' => 'us-ca-san-francisco',
            'locality' => 'San Francisco',
            'region_code' => 'CA',
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'inbound_count' => 1,
            'outbound_count' => 1,
        ]);
        $this->aggregate($account, [
            'bucket_started_at' => '2026-08-20 08:00:00',
            'location_key' => 'outside-range',
            'inbound_count' => 25,
        ]);
        $this->aggregate($otherAccount, [
            'bucket_started_at' => '2026-08-31 08:00:00',
            'location_key' => 'other-tenant',
            'inbound_count' => 50,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/call-geography?range=7d")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.capability.available', true)
            ->assertJsonPath('data.coverage.total_calls', 5)
            ->assertJsonPath('data.coverage.located_calls', 5)
            ->assertJsonPath('data.coverage.percentage', 100)
            ->assertJsonCount(2, 'data.locations')
            ->assertJsonPath('data.locations.0.label', 'Seattle, WA, US')
            ->assertJsonPath('data.locations.0.total', 3)
            ->assertJsonPath('data.locations.0.inbound', 2)
            ->assertJsonPath('data.locations.0.outbound', 1)
            ->assertJsonMissing(['other-tenant'])
            ->assertJsonMissing(['outside-range']);
    }

    public function test_rejects_invalid_ranges_and_other_organization_access(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/call-geography?range=year")
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('range');

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/call-geography")
            ->assertNotFound();
    }

    /** @param array<string, mixed> $attributes */
    private function aggregate(SwitchAccount $account, array $attributes): CallGeographyAggregate
    {
        return CallGeographyAggregate::query()->create([
            'switch_account_id' => $account->getKey(),
            'bucket_started_at' => '2026-08-31 08:00:00',
            'location_key' => 'test-location',
            'locality' => null,
            'region_code' => null,
            'country_code' => 'US',
            'latitude' => 39.8283,
            'longitude' => -98.5795,
            'precision' => 'numbering_plan',
            'inbound_count' => 0,
            'outbound_count' => 0,
            'source' => 'approved-test-source',
            'source_updated_at' => '2026-08-31 11:00:00',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{User, SwitchAccount}
     */
    private function accessibleAccount(array $attributes = []): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, [
            'role' => OrganizationRole::ReadOnlyUser->value,
        ]);

        return [$user, SwitchAccount::factory()->for($organization)->create($attributes)];
    }
}

final class AvailableCallGeographyProvider implements CallGeographyProvider
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function source(): string
    {
        return 'approved-test-source';
    }

    public function locate(string $e164Number): ?CallGeographyLocation
    {
        return null;
    }
}
