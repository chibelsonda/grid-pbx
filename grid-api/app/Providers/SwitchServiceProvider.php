<?php

namespace App\Providers;

use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Gateways\CrossbarSwitchDeviceGateway;
use App\Domains\SwitchSynchronization\Contracts\SwitchExtensionGateway;
use App\Domains\SwitchSynchronization\Gateways\CrossbarSwitchExtensionGateway;
use GridPbx\Switch\ApiKeyTokenProvider;
use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Resources\AccountResourceClient;
use GridPbx\Switch\Resources\DeviceResourceClient;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class SwitchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SwitchDeviceGateway::class, CrossbarSwitchDeviceGateway::class);
        $this->app->bind(SwitchExtensionGateway::class, CrossbarSwitchExtensionGateway::class);

        $this->app->singleton(ClientInterface::class, fn () => new Client);

        $this->app->singleton(SwitchConfig::class, fn () => new SwitchConfig(
            baseUrl: (string) config('switch.base_url'),
            apiKey: (string) config('switch.api_key'),
            timeout: (float) config('switch.timeout'),
        ));

        $this->app->singleton(TokenProvider::class, fn ($app) => new ApiKeyTokenProvider(
            $app->make(ClientInterface::class),
            $app->make(SwitchConfig::class),
        ));

        $this->app->singleton(SwitchClient::class, fn ($app) => new SwitchClient(
            $app->make(ClientInterface::class),
            $app->make(SwitchConfig::class),
            $app->make(TokenProvider::class),
        ));

        $this->app->singleton(AccountResourceClient::class, fn ($app) => new AccountResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(DeviceResourceClient::class, fn ($app) => new DeviceResourceClient(
            $app->make(SwitchClient::class),
        ));
    }
}
