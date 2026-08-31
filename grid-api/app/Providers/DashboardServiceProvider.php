<?php

namespace App\Providers;

use App\Domains\Dashboard\Contracts\CallGeographyProvider;
use App\Domains\Dashboard\Providers\UnconfiguredCallGeographyProvider;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CallGeographyProvider::class,
            UnconfiguredCallGeographyProvider::class,
        );
    }
}
