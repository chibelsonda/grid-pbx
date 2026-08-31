<?php

namespace App\Domains\Dashboard\Dto;

final readonly class CallGeographyEnrichmentResult
{
    public function __construct(
        public bool $available,
        public string $source,
        public int $scannedCalls = 0,
        public int $eligibleCalls = 0,
        public int $locatedCalls = 0,
        public int $aggregateLocations = 0,
        public ?string $reason = null,
    ) {}
}
