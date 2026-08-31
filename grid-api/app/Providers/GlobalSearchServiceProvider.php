<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class GlobalSearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('global-search', function (Request $request): Limit {
            $key = implode('|', [
                (string) $request->user()?->getAuthIdentifier(),
                (string) $request->route('account'),
                (string) $request->ip(),
            ]);

            return Limit::perMinute(120)->by($key);
        });
    }
}
