<?php

namespace App\Providers;

use GridPbx\Kazoo\ApiKeyTokenProvider;
use GridPbx\Kazoo\Contracts\TokenProvider;
use GridPbx\Kazoo\KazooClient;
use GridPbx\Kazoo\KazooConfig;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class KazooServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, fn () => new Client);

        $this->app->singleton(KazooConfig::class, fn () => new KazooConfig(
            baseUrl: (string) config('kazoo.base_url'),
            apiKey: (string) config('kazoo.api_key'),
            timeout: (float) config('kazoo.timeout'),
        ));

        $this->app->singleton(TokenProvider::class, fn ($app) => new ApiKeyTokenProvider(
            $app->make(ClientInterface::class),
            $app->make(KazooConfig::class),
        ));

        $this->app->singleton(KazooClient::class, fn ($app) => new KazooClient(
            $app->make(ClientInterface::class),
            $app->make(KazooConfig::class),
            $app->make(TokenProvider::class),
        ));
    }
}
