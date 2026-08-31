<?php

namespace App\Domains\Dashboard\Providers;

use App\Domains\Dashboard\Contracts\CallGeographyProvider;
use App\Domains\Dashboard\Dto\CallGeographyLocation;

final class UnconfiguredCallGeographyProvider implements CallGeographyProvider
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function source(): string
    {
        return 'unconfigured';
    }

    public function locate(string $e164Number): ?CallGeographyLocation
    {
        return null;
    }
}
