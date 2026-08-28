<?php

namespace App\Providers;

use App\Domains\CallDetailRecords\Contracts\SwitchCallDetailRecordGateway;
use App\Domains\CallDetailRecords\Gateways\CrossbarSwitchCallDetailRecordGateway;
use App\Domains\CallRouting\Contracts\SwitchCallflowGateway;
use App\Domains\CallRouting\Gateways\CrossbarSwitchCallflowGateway;
use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Gateways\CrossbarSwitchDeviceGateway;
use App\Domains\Directories\Contracts\SwitchDirectoryGateway;
use App\Domains\Directories\Gateways\CrossbarSwitchDirectoryGateway;
use App\Domains\Extensions\Contracts\SwitchExtensionProvisioningGateway;
use App\Domains\Extensions\Gateways\CrossbarSwitchExtensionProvisioningGateway;
use App\Domains\Groups\Contracts\SwitchGroupGateway;
use App\Domains\Groups\Gateways\CrossbarSwitchGroupGateway;
use App\Domains\Media\Contracts\SwitchMediaGateway;
use App\Domains\Media\Gateways\CrossbarSwitchMediaGateway;
use App\Domains\Menus\Contracts\SwitchMenuGateway;
use App\Domains\Menus\Gateways\CrossbarSwitchMenuGateway;
use App\Domains\PhoneNumbers\Contracts\SwitchPhoneNumberGateway;
use App\Domains\PhoneNumbers\Gateways\CrossbarSwitchPhoneNumberGateway;
use App\Domains\Queues\Contracts\SwitchAgentGateway;
use App\Domains\Queues\Contracts\SwitchQueueGateway;
use App\Domains\Queues\Gateways\CrossbarSwitchAgentGateway;
use App\Domains\Queues\Gateways\CrossbarSwitchQueueGateway;
use App\Domains\SwitchSynchronization\Contracts\SwitchExtensionGateway;
use App\Domains\SwitchSynchronization\Gateways\CrossbarSwitchExtensionGateway;
use App\Domains\Voicemail\Contracts\SwitchVoicemailBoxGateway;
use App\Domains\Voicemail\Contracts\SwitchVoicemailGreetingGateway;
use App\Domains\Voicemail\Contracts\SwitchVoicemailMessageGateway;
use App\Domains\Voicemail\Gateways\CrossbarSwitchVoicemailBoxGateway;
use App\Domains\Voicemail\Gateways\CrossbarSwitchVoicemailGreetingGateway;
use App\Domains\Voicemail\Gateways\CrossbarSwitchVoicemailMessageGateway;
use GridPbx\Switch\ApiKeyTokenProvider;
use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Resources\AccountResourceClient;
use GridPbx\Switch\Resources\AgentResourceClient;
use GridPbx\Switch\Resources\CallDetailRecordResourceClient;
use GridPbx\Switch\Resources\CallflowResourceClient;
use GridPbx\Switch\Resources\DeviceResourceClient;
use GridPbx\Switch\Resources\DirectoryResourceClient;
use GridPbx\Switch\Resources\GroupResourceClient;
use GridPbx\Switch\Resources\MediaResourceClient;
use GridPbx\Switch\Resources\MenuResourceClient;
use GridPbx\Switch\Resources\PhoneNumberResourceClient;
use GridPbx\Switch\Resources\QueueResourceClient;
use GridPbx\Switch\Resources\UserResourceClient;
use GridPbx\Switch\Resources\VoicemailBoxResourceClient;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class SwitchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SwitchCallflowGateway::class, CrossbarSwitchCallflowGateway::class);
        $this->app->bind(SwitchCallDetailRecordGateway::class, CrossbarSwitchCallDetailRecordGateway::class);
        $this->app->bind(SwitchDeviceGateway::class, CrossbarSwitchDeviceGateway::class);
        $this->app->bind(SwitchDirectoryGateway::class, CrossbarSwitchDirectoryGateway::class);
        $this->app->bind(SwitchExtensionGateway::class, CrossbarSwitchExtensionGateway::class);
        $this->app->bind(SwitchExtensionProvisioningGateway::class, CrossbarSwitchExtensionProvisioningGateway::class);
        $this->app->bind(SwitchGroupGateway::class, CrossbarSwitchGroupGateway::class);
        $this->app->bind(SwitchMediaGateway::class, CrossbarSwitchMediaGateway::class);
        $this->app->bind(SwitchMenuGateway::class, CrossbarSwitchMenuGateway::class);
        $this->app->bind(SwitchPhoneNumberGateway::class, CrossbarSwitchPhoneNumberGateway::class);
        $this->app->bind(SwitchQueueGateway::class, CrossbarSwitchQueueGateway::class);
        $this->app->bind(SwitchAgentGateway::class, CrossbarSwitchAgentGateway::class);
        $this->app->bind(SwitchVoicemailBoxGateway::class, CrossbarSwitchVoicemailBoxGateway::class);
        $this->app->bind(SwitchVoicemailMessageGateway::class, CrossbarSwitchVoicemailMessageGateway::class);
        $this->app->bind(SwitchVoicemailGreetingGateway::class, CrossbarSwitchVoicemailGreetingGateway::class);

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

        $this->app->singleton(AgentResourceClient::class, fn ($app) => new AgentResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(CallflowResourceClient::class, fn ($app) => new CallflowResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(CallDetailRecordResourceClient::class, fn ($app) => new CallDetailRecordResourceClient(
            $app->make(SwitchClient::class),
            (int) config('switch.cdr_page_size'),
        ));

        $this->app->singleton(DeviceResourceClient::class, fn ($app) => new DeviceResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(DirectoryResourceClient::class, fn ($app) => new DirectoryResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(VoicemailBoxResourceClient::class, fn ($app) => new VoicemailBoxResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(MediaResourceClient::class, fn ($app) => new MediaResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(MenuResourceClient::class, fn ($app) => new MenuResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(GroupResourceClient::class, fn ($app) => new GroupResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(PhoneNumberResourceClient::class, fn ($app) => new PhoneNumberResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(QueueResourceClient::class, fn ($app) => new QueueResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(UserResourceClient::class, fn ($app) => new UserResourceClient(
            $app->make(SwitchClient::class),
        ));
    }
}
