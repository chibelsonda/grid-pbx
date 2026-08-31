<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Dashboard\Contracts\CallGeographyProvider;
use App\Domains\Dashboard\Dto\CallGeographyEnrichmentResult;
use App\Domains\Dashboard\Dto\CallGeographyLocation;
use App\Domains\Dashboard\Models\CallGeographyAggregate;
use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CallGeographyEnrichmentService
{
    public function __construct(
        private readonly CallGeographyProvider $provider,
        private readonly CallGeographyNumberNormalizer $numbers,
    ) {}

    public function enrich(
        SwitchAccount $account,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): CallGeographyEnrichmentResult {
        $source = trim((string) config('dashboard.call_geography.source', 'unconfigured'));

        if (! config('dashboard.call_geography.enabled', false)) {
            return new CallGeographyEnrichmentResult(
                available: false,
                source: $source,
                reason: 'Call geography enrichment is disabled.',
            );
        }

        if (! $this->provider->isAvailable() || $source === '' || $source === 'unconfigured') {
            return new CallGeographyEnrichmentResult(
                available: false,
                source: $source,
                reason: 'An approved call geography provider is not configured.',
            );
        }

        if ($this->provider->source() !== $source) {
            return new CallGeographyEnrichmentResult(
                available: false,
                source: $source,
                reason: 'The configured geography source does not match the active provider.',
            );
        }

        if ($end->lessThanOrEqualTo($start) || $start->diffInDays($end) > 31) {
            throw new InvalidArgumentException('Call geography enrichment requires a positive period of at most 31 days.');
        }

        $scannedCalls = 0;
        $eligibleCalls = 0;
        $locatedCalls = 0;
        $locationCache = [];
        $aggregates = [];
        $timezone = $account->timezone ?: (string) config('app.timezone', 'UTC');

        SwitchCallDetailRecord::query()
            ->whereBelongsTo($account, 'switchAccount')
            ->where('started_at', '>=', $start->utc()->format('Y-m-d H:i:s'))
            ->where('started_at', '<', $end->utc()->format('Y-m-d H:i:s'))
            ->whereIn('direction', ['inbound', 'outbound'])
            ->select([
                'call_detail_record_id',
                'direction',
                'caller_id_number',
                'callee_id_number',
                'started_at',
            ])
            ->lazyById(500, 'call_detail_record_id')
            ->each(function (SwitchCallDetailRecord $record) use (
                &$aggregates,
                &$eligibleCalls,
                &$locatedCalls,
                &$locationCache,
                &$scannedCalls,
                $account,
                $timezone,
            ): void {
                $scannedCalls++;
                $number = $this->numbers->normalize(
                    $record->direction === 'inbound'
                        ? $record->caller_id_number
                        : $record->callee_id_number,
                );

                if ($number === null) {
                    return;
                }

                $eligibleCalls++;
                $numberHash = hash('sha256', $number);

                if (! array_key_exists($numberHash, $locationCache)) {
                    $locationCache[$numberHash] = $this->provider->locate($number);
                }

                $location = $locationCache[$numberHash];

                if (! $location instanceof CallGeographyLocation) {
                    return;
                }

                $locatedCalls++;
                $bucket = $record->started_at
                    ->toImmutable()
                    ->setTimezone($timezone)
                    ->startOfDay()
                    ->utc();
                $aggregateKey = $bucket->format('Y-m-d H:i:s').'|'.$location->key;
                $aggregate = $aggregates[$aggregateKey] ?? $this->aggregateRow($account, $bucket, $location);
                $countKey = $record->direction === 'inbound' ? 'inbound_count' : 'outbound_count';
                $aggregate[$countKey]++;
                $aggregates[$aggregateKey] = $aggregate;
            });

        DB::transaction(function () use ($account, $aggregates, $end, $source, $start): void {
            CallGeographyAggregate::query()
                ->whereBelongsTo($account, 'switchAccount')
                ->where('source', $source)
                ->where('bucket_started_at', '>=', $start->utc()->format('Y-m-d H:i:s'))
                ->where('bucket_started_at', '<', $end->utc()->format('Y-m-d H:i:s'))
                ->delete();

            foreach ($aggregates as $aggregate) {
                CallGeographyAggregate::query()->create($aggregate);
            }
        });

        return new CallGeographyEnrichmentResult(
            available: true,
            source: $source,
            scannedCalls: $scannedCalls,
            eligibleCalls: $eligibleCalls,
            locatedCalls: $locatedCalls,
            aggregateLocations: count($aggregates),
        );
    }

    /** @return array<string, mixed> */
    private function aggregateRow(
        SwitchAccount $account,
        CarbonImmutable $bucket,
        CallGeographyLocation $location,
    ): array {
        return [
            'switch_account_id' => $account->getKey(),
            'bucket_started_at' => $bucket,
            'location_key' => $location->key,
            'locality' => $location->locality,
            'region_code' => $location->regionCode,
            'country_code' => $location->countryCode,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'precision' => $location->precision,
            'inbound_count' => 0,
            'outbound_count' => 0,
            'source' => $this->provider->source(),
            'source_updated_at' => now(),
        ];
    }
}
