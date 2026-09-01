<?php

namespace App\Providers;

use App\Domains\CallRouting\Contracts\DisaOperationalGuard;
use App\Domains\CallRouting\Gateways\UnavailableDisaOperationalGuard;
use Illuminate\Support\ServiceProvider;

class CallRoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            DisaOperationalGuard::class,
            UnavailableDisaOperationalGuard::class,
        );
    }
}
