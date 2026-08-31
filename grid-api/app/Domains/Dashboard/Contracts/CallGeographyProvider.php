<?php

namespace App\Domains\Dashboard\Contracts;

use App\Domains\Dashboard\Dto\CallGeographyLocation;

interface CallGeographyProvider
{
    public function isAvailable(): bool;

    public function source(): string;

    public function locate(string $e164Number): ?CallGeographyLocation;
}
