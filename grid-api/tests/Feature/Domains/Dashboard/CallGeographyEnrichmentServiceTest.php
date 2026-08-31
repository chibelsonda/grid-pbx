<?php

namespace Tests\Feature\Domains\Dashboard;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Dashboard\Contracts\CallGeographyProvider;
use App\Domains\Dashboard\Dto\CallGeographyLocation;
use App\Domains\Dashboard\Models\CallGeographyAggregate;
use App\Domains\Dashboard\Services\CallGeographyEnrichmentService;
use App\Domains\Dashboard\Services\CallGeographyNumberNormalizer;
use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CallGeographyEnrichmentServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uses_the_remote_party_and_rebuilds_privacy_safe_aggregates_idempotently(): void
    {
        $this->travelTo('2026-08-31 12:00:00');
        $account = SwitchAccount::factory()->create(['timezone' => 'UTC']);
        $provider = new FakeCallGeographyProvider([
            '+14155550100' => new CallGeographyLocation(
                key: 'us-ca-san-francisco',
                locality: 'San Francisco',
                regionCode: 'CA',
                countryCode: 'US',
                latitude: 37.7749,
                longitude: -122.4194,
            ),
            '+442079460018' => new CallGeographyLocation(
                key: 'gb-london',
                locality: 'London',
                regionCode: null,
                countryCode: 'GB',
                latitude: 51.5074,
                longitude: -0.1278,
            ),
        ]);
        config()->set('dashboard.call_geography.enabled', true);
        config()->set('dashboard.call_geography.source', $provider->source());

        $this->createCall($account, 'inbound', 'tel:+1 (415) 555-0100', '1001');
        $this->createCall($account, 'inbound', '+1 415-555-0100', '1002');
        $this->createCall($account, 'outbound', '+12065550199', 'sip:+442079460018@example.test');
        $this->createCall($account, 'outbound', '+12065550199', '1003');

        $first = $this->service($provider)->enrich(
            $account,
            CarbonImmutable::parse('2026-08-31 00:00:00 UTC'),
            CarbonImmutable::parse('2026-09-01 00:00:00 UTC'),
        );
        $second = $this->service($provider)->enrich(
            $account,
            CarbonImmutable::parse('2026-08-31 00:00:00 UTC'),
            CarbonImmutable::parse('2026-09-01 00:00:00 UTC'),
        );

        $this->assertTrue($first->available);
        $this->assertSame(4, $first->scannedCalls);
        $this->assertSame(3, $first->eligibleCalls);
        $this->assertSame(3, $first->locatedCalls);
        $this->assertSame(2, $first->aggregateLocations);
        $this->assertSame(2, $second->aggregateLocations);
        $this->assertSame([
            '+14155550100',
            '+442079460018',
            '+14155550100',
            '+442079460018',
        ], $provider->lookups);

        $this->assertDatabaseCount('switch_call_geography_aggregates', 2);
        $this->assertDatabaseHas('switch_call_geography_aggregates', [
            'switch_account_id' => $account->getKey(),
            'location_key' => 'us-ca-san-francisco',
            'inbound_count' => 2,
            'outbound_count' => 0,
            'source' => 'approved-test-source',
        ]);
        $this->assertDatabaseHas('switch_call_geography_aggregates', [
            'switch_account_id' => $account->getKey(),
            'location_key' => 'gb-london',
            'inbound_count' => 0,
            'outbound_count' => 1,
            'source' => 'approved-test-source',
        ]);
        $this->assertSame(0, CallGeographyAggregate::query()
            ->whereNotNull('switch_account_id')
            ->where(function ($query): void {
                $query->where('location_key', 'like', '%14155550100%')
                    ->orWhere('location_key', 'like', '%442079460018%');
            })
            ->count());
    }

    public function test_leaves_existing_aggregates_untouched_when_provider_is_unavailable(): void
    {
        $account = SwitchAccount::factory()->create();
        $provider = new FakeCallGeographyProvider([], available: false);
        config()->set('dashboard.call_geography.enabled', true);
        config()->set('dashboard.call_geography.source', $provider->source());
        CallGeographyAggregate::query()->create([
            'switch_account_id' => $account->getKey(),
            'bucket_started_at' => '2026-08-31 00:00:00',
            'location_key' => 'existing-location',
            'country_code' => 'US',
            'latitude' => 39.8283,
            'longitude' => -98.5795,
            'inbound_count' => 1,
            'outbound_count' => 0,
            'source' => $provider->source(),
            'source_updated_at' => now(),
        ]);

        $result = $this->service($provider)->enrich(
            $account,
            CarbonImmutable::parse('2026-08-31 00:00:00 UTC'),
            CarbonImmutable::parse('2026-09-01 00:00:00 UTC'),
        );

        $this->assertFalse($result->available);
        $this->assertSame('An approved call geography provider is not configured.', $result->reason);
        $this->assertDatabaseHas('switch_call_geography_aggregates', [
            'location_key' => 'existing-location',
            'inbound_count' => 1,
        ]);
    }

    public function test_rejects_non_positive_or_unbounded_enrichment_periods(): void
    {
        $account = SwitchAccount::factory()->create();
        $provider = new FakeCallGeographyProvider([]);
        config()->set('dashboard.call_geography.enabled', true);
        config()->set('dashboard.call_geography.source', $provider->source());

        $this->expectException(InvalidArgumentException::class);

        $this->service($provider)->enrich(
            $account,
            CarbonImmutable::parse('2026-07-01 00:00:00 UTC'),
            CarbonImmutable::parse('2026-09-01 00:00:00 UTC'),
        );
    }

    private function service(CallGeographyProvider $provider): CallGeographyEnrichmentService
    {
        return new CallGeographyEnrichmentService(
            $provider,
            new CallGeographyNumberNormalizer,
        );
    }

    private function createCall(
        SwitchAccount $account,
        string $direction,
        string $caller,
        string $callee,
    ): SwitchCallDetailRecord {
        return SwitchCallDetailRecord::factory()->for($account)->create([
            'direction' => $direction,
            'caller_id_number' => $caller,
            'callee_id_number' => $callee,
            'started_at' => '2026-08-31 08:00:00',
        ]);
    }
}

final class FakeCallGeographyProvider implements CallGeographyProvider
{
    /** @var list<string> */
    public array $lookups = [];

    /** @param array<string, CallGeographyLocation> $locations */
    public function __construct(
        private readonly array $locations,
        private readonly bool $available = true,
    ) {}

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function source(): string
    {
        return 'approved-test-source';
    }

    public function locate(string $e164Number): ?CallGeographyLocation
    {
        $this->lookups[] = $e164Number;

        return $this->locations[$e164Number] ?? null;
    }
}
