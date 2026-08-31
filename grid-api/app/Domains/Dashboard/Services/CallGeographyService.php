<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Dashboard\Contracts\CallGeographyProvider;
use App\Domains\Dashboard\Models\CallGeographyAggregate;
use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class CallGeographyService
{
    private const DISCLOSURE = 'Estimated from telephone numbering-plan assignments; this is not a caller\'s live or precise location.';

    public function __construct(
        private readonly CallAnalyticsPeriodService $periods,
        private readonly CallGeographyProvider $provider,
    ) {}

    /** @return array<string, mixed> */
    public function get(SwitchAccount $account, string $range): array
    {
        $period = $this->periods->resolve($account, $range);
        $source = trim((string) config('dashboard.call_geography.source', 'unconfigured'));
        $available = (bool) config('dashboard.call_geography.enabled', false)
            && $source !== ''
            && $source !== 'unconfigured'
            && $this->provider->isAvailable()
            && $this->provider->source() === $source;

        if (! $available) {
            return $this->response(
                range: $range,
                period: $period,
                status: 'unavailable',
                available: false,
                source: null,
                reason: 'An approved numbering-plan geography source and privacy policy are required.',
            );
        }

        $query = CallGeographyAggregate::query()
            ->where('switch_account_id', $account->getKey())
            ->where('source', $source)
            ->where('bucket_started_at', '>=', $this->periods->databaseTimestamp($period['start']))
            ->where('bucket_started_at', '<', $this->periods->databaseTimestamp($period['end']));
        $totalCalls = $account->callDetailRecords()
            ->where('started_at', '>=', $this->periods->databaseTimestamp($period['start']))
            ->where('started_at', '<', $this->periods->databaseTimestamp($period['end']))
            ->whereIn('direction', ['inbound', 'outbound'])
            ->count();
        $locatedCalls = (int) (clone $query)
            ->selectRaw('COALESCE(SUM(inbound_count + outbound_count), 0) AS aggregate_count')
            ->value('aggregate_count');
        $dataAsOf = (clone $query)->max('source_updated_at');
        $maximumLocations = max(1, min(
            (int) config('dashboard.call_geography.maximum_locations', 100),
            250,
        ));
        $locations = $this->locations($query, $maximumLocations);

        return $this->response(
            range: $range,
            period: $period,
            status: $locations === [] ? 'empty' : 'ready',
            available: true,
            source: $source,
            reason: $locations === []
                ? 'No approved geography aggregates are available for this period.'
                : null,
            totalCalls: $totalCalls,
            locatedCalls: min($locatedCalls, $totalCalls),
            locations: $locations,
            dataAsOf: is_string($dataAsOf)
                ? CarbonImmutable::parse($dataAsOf)->toIso8601String()
                : null,
        );
    }

    /**
     * @param  Builder<CallGeographyAggregate>  $query
     * @return array<int, array<string, float|int|string|null>>
     */
    private function locations(Builder $query, int $limit): array
    {
        return $query
            ->select([
                'location_key',
                'locality',
                'region_code',
                'country_code',
                'latitude',
                'longitude',
                'precision',
            ])
            ->selectRaw('SUM(inbound_count) AS inbound_count')
            ->selectRaw('SUM(outbound_count) AS outbound_count')
            ->groupBy([
                'location_key',
                'locality',
                'region_code',
                'country_code',
                'latitude',
                'longitude',
                'precision',
            ])
            ->orderByRaw('SUM(inbound_count + outbound_count) DESC')
            ->limit($limit)
            ->get()
            ->map(function (CallGeographyAggregate $location): array {
                $inbound = (int) $location->getAttribute('inbound_count');
                $outbound = (int) $location->getAttribute('outbound_count');

                return [
                    'key' => $location->location_key,
                    'label' => $this->label($location),
                    'locality' => $location->locality,
                    'region_code' => $location->region_code,
                    'country_code' => $location->country_code,
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'precision' => $location->precision,
                    'total' => $inbound + $outbound,
                    'inbound' => $inbound,
                    'outbound' => $outbound,
                ];
            })
            ->all();
    }

    private function label(CallGeographyAggregate $location): string
    {
        return collect([$location->locality, $location->region_code, $location->country_code])
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->implode(', ');
    }

    /**
     * @param  array{timezone: string, granularity: string, start: mixed, end: mixed, buckets: array<mixed>}  $period
     * @param  array<int, array<string, float|int|string|null>>  $locations
     * @return array<string, mixed>
     */
    private function response(
        string $range,
        array $period,
        string $status,
        bool $available,
        ?string $source,
        ?string $reason,
        int $totalCalls = 0,
        int $locatedCalls = 0,
        array $locations = [],
        ?string $dataAsOf = null,
    ): array {
        return [
            'generated_at' => now()->toIso8601String(),
            'data_as_of' => $dataAsOf,
            'range' => $range,
            'timezone' => $period['timezone'],
            'from' => $period['start']->toIso8601String(),
            'to' => $period['end']->toIso8601String(),
            'status' => $status,
            'capability' => [
                'available' => $available,
                'source' => $source,
                'reason' => $reason,
            ],
            'coverage' => [
                'total_calls' => $totalCalls,
                'located_calls' => $locatedCalls,
                'percentage' => $totalCalls === 0
                    ? 0.0
                    : round(($locatedCalls / $totalCalls) * 100, 1),
            ],
            'locations' => $locations,
            'disclosure' => self::DISCLOSURE,
        ];
    }
}
