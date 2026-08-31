<?php

namespace App\Domains\Dashboard\Dto;

use InvalidArgumentException;

final readonly class CallGeographyLocation
{
    public function __construct(
        public string $key,
        public ?string $locality,
        public ?string $regionCode,
        public string $countryCode,
        public float $latitude,
        public float $longitude,
        public string $precision = 'numbering_plan',
    ) {
        if ($key === '' || mb_strlen($key) > 64) {
            throw new InvalidArgumentException('The geography location key must contain 1 to 64 characters.');
        }

        if (! preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new InvalidArgumentException('The geography country code must use ISO 3166-1 alpha-2 format.');
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('The geography coordinates are outside the valid range.');
        }
    }
}
