<?php

namespace App\Providers;

use App\Domains\Blacklists\Contracts\SwitchBlacklistGateway;
use App\Domains\Blacklists\Gateways\CrossbarSwitchBlacklistGateway;
use App\Domains\CallDetailRecords\Contracts\SwitchCallDetailRecordGateway;
use App\Domains\CallDetailRecords\Gateways\CrossbarSwitchCallDetailRecordGateway;
use App\Domains\CallRouting\Contracts\SwitchCallflowGateway;
use App\Domains\CallRouting\Gateways\CrossbarSwitchCallflowGateway;
use App\Domains\Conferences\Contracts\SwitchConferenceGateway;
use App\Domains\Conferences\Gateways\CrossbarSwitchConferenceGateway;
use App\Domains\Devices\Contracts\ManufacturerProvisioningEnrollmentGateway;
use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Contracts\SwitchProvisioningCatalogGateway;
use App\Domains\Devices\Gateways\CrossbarSwitchDeviceGateway;
use App\Domains\Devices\Gateways\ProvisionerSwitchProvisioningCatalogGateway;
use App\Domains\Devices\Gateways\UnavailableManufacturerProvisioningEnrollmentGateway;
use App\Domains\Devices\Gateways\UnavailableSwitchProvisioningCatalogGateway;
use App\Domains\Devices\Services\DeviceMetaflowPolicy;
use App\Domains\Directories\Contracts\SwitchDirectoryGateway;
use App\Domains\Directories\Gateways\CrossbarSwitchDirectoryGateway;
use App\Domains\Extensions\Contracts\SwitchExtensionProvisioningGateway;
use App\Domains\Extensions\Gateways\CrossbarSwitchExtensionProvisioningGateway;
use App\Domains\Faxes\Contracts\SwitchFaxBoxGateway;
use App\Domains\Faxes\Contracts\SwitchFaxGateway;
use App\Domains\Faxes\Gateways\CrossbarSwitchFaxBoxGateway;
use App\Domains\Faxes\Gateways\CrossbarSwitchFaxGateway;
use App\Domains\Groups\Contracts\SwitchGroupGateway;
use App\Domains\Groups\Gateways\CrossbarSwitchGroupGateway;
use App\Domains\LineKeys\Contracts\SwitchLineKeyGateway;
use App\Domains\LineKeys\Gateways\CrossbarSwitchLineKeyGateway;
use App\Domains\Media\Contracts\SwitchMediaGateway;
use App\Domains\Media\Gateways\CrossbarSwitchMediaGateway;
use App\Domains\Menus\Contracts\SwitchMenuGateway;
use App\Domains\Menus\Gateways\CrossbarSwitchMenuGateway;
use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Gateways\CrossbarSwitchAccountGateway;
use App\Domains\PhoneNumbers\Contracts\SwitchPhoneNumberGateway;
use App\Domains\PhoneNumbers\Gateways\CrossbarSwitchPhoneNumberGateway;
use App\Domains\Queues\Contracts\SwitchAgentGateway;
use App\Domains\Queues\Contracts\SwitchQueueGateway;
use App\Domains\Queues\Gateways\CrossbarSwitchAgentGateway;
use App\Domains\Queues\Gateways\CrossbarSwitchQueueGateway;
use App\Domains\Recordings\Contracts\SwitchRecordingGateway;
use App\Domains\Recordings\Gateways\CrossbarSwitchRecordingGateway;
use App\Domains\Services\Contracts\SwitchServiceGateway;
use App\Domains\Services\Gateways\CrossbarSwitchServiceGateway;
use App\Domains\SwitchSynchronization\Contracts\SwitchExtensionGateway;
use App\Domains\SwitchSynchronization\Gateways\CrossbarSwitchExtensionGateway;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleGateway;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleSetGateway;
use App\Domains\TemporalRouting\Gateways\CrossbarSwitchTemporalRuleGateway;
use App\Domains\TemporalRouting\Gateways\CrossbarSwitchTemporalRuleSetGateway;
use App\Domains\Voicemail\Contracts\SwitchVoicemailBoxGateway;
use App\Domains\Voicemail\Contracts\SwitchVoicemailGreetingGateway;
use App\Domains\Voicemail\Contracts\SwitchVoicemailMessageGateway;
use App\Domains\Voicemail\Gateways\CrossbarSwitchVoicemailBoxGateway;
use App\Domains\Voicemail\Gateways\CrossbarSwitchVoicemailGreetingGateway;
use App\Domains\Voicemail\Gateways\CrossbarSwitchVoicemailMessageGateway;
use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\Domains\Agents\AgentResourceClient;
use GridPbx\Switch\Domains\Blacklists\BlacklistResourceClient;
use GridPbx\Switch\Domains\CallDetailRecords\CallDetailRecordResourceClient;
use GridPbx\Switch\Domains\Callflows\CallflowResourceClient;
use GridPbx\Switch\Domains\Conferences\ConferenceResourceClient;
use GridPbx\Switch\Domains\Devices\DeviceResourceClient;
use GridPbx\Switch\Domains\Directories\DirectoryResourceClient;
use GridPbx\Switch\Domains\Faxes\FaxBoxResourceClient;
use GridPbx\Switch\Domains\Faxes\FaxMessageResourceClient;
use GridPbx\Switch\Domains\Groups\GroupResourceClient;
use GridPbx\Switch\Domains\LineKeys\LineKeyResourceClient;
use GridPbx\Switch\Domains\Media\MediaResourceClient;
use GridPbx\Switch\Domains\Menus\MenuResourceClient;
use GridPbx\Switch\Domains\PhoneNumbers\PhoneNumberResourceClient;
use GridPbx\Switch\Domains\Provisioning\ProvisionerClient;
use GridPbx\Switch\Domains\Provisioning\ProvisionerConfig;
use GridPbx\Switch\Domains\Provisioning\ProvisioningCatalogResourceClient;
use GridPbx\Switch\Domains\Queues\QueueResourceClient;
use GridPbx\Switch\Domains\Recordings\RecordingResourceClient;
use GridPbx\Switch\Domains\Services\ServiceResourceClient;
use GridPbx\Switch\Domains\TemporalRules\TemporalRuleResourceClient;
use GridPbx\Switch\Domains\TemporalRuleSets\TemporalRuleSetResourceClient;
use GridPbx\Switch\Domains\Users\UserResourceClient;
use GridPbx\Switch\Domains\Voicemail\VoicemailBoxResourceClient;
use GridPbx\Switch\Shared\Authentication\ApiKeyTokenProvider;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class SwitchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(DeviceMetaflowPolicy::class);
        $this->app->bind(SwitchCallflowGateway::class, CrossbarSwitchCallflowGateway::class);
        $this->app->bind(SwitchAccountGateway::class, CrossbarSwitchAccountGateway::class);
        $this->app->bind(SwitchConferenceGateway::class, CrossbarSwitchConferenceGateway::class);
        $this->app->bind(SwitchBlacklistGateway::class, CrossbarSwitchBlacklistGateway::class);
        $this->app->bind(SwitchCallDetailRecordGateway::class, CrossbarSwitchCallDetailRecordGateway::class);
        $this->app->bind(SwitchDeviceGateway::class, CrossbarSwitchDeviceGateway::class);
        $this->app->singleton(
            ManufacturerProvisioningEnrollmentGateway::class,
            UnavailableManufacturerProvisioningEnrollmentGateway::class,
        );
        $this->app->singleton(SwitchProvisioningCatalogGateway::class, function ($app) {
            $baseUrl = trim((string) config('switch.provisioner_url'));

            if ($baseUrl === '') {
                return new UnavailableSwitchProvisioningCatalogGateway;
            }

            $client = new ProvisionerClient(
                $app->make(ClientInterface::class),
                new ProvisionerConfig(
                    baseUrl: $baseUrl,
                    authType: (string) config('switch.provisioner.auth_type'),
                    token: config('switch.provisioner.token'),
                    username: config('switch.provisioner.username'),
                    password: config('switch.provisioner.password'),
                    headerName: (string) config('switch.provisioner.header_name'),
                    timeout: (float) config('switch.provisioner.timeout'),
                    verifyTls: (bool) config('switch.provisioner.verify_tls'),
                ),
            );

            return new ProvisionerSwitchProvisioningCatalogGateway(
                new ProvisioningCatalogResourceClient($client),
            );
        });
        $this->app->bind(SwitchDirectoryGateway::class, CrossbarSwitchDirectoryGateway::class);
        $this->app->bind(SwitchExtensionGateway::class, CrossbarSwitchExtensionGateway::class);
        $this->app->bind(SwitchExtensionProvisioningGateway::class, CrossbarSwitchExtensionProvisioningGateway::class);
        $this->app->bind(SwitchFaxBoxGateway::class, CrossbarSwitchFaxBoxGateway::class);
        $this->app->bind(SwitchFaxGateway::class, CrossbarSwitchFaxGateway::class);
        $this->app->bind(SwitchGroupGateway::class, CrossbarSwitchGroupGateway::class);
        $this->app->bind(SwitchLineKeyGateway::class, CrossbarSwitchLineKeyGateway::class);
        $this->app->bind(SwitchMediaGateway::class, CrossbarSwitchMediaGateway::class);
        $this->app->bind(SwitchMenuGateway::class, CrossbarSwitchMenuGateway::class);
        $this->app->bind(SwitchPhoneNumberGateway::class, CrossbarSwitchPhoneNumberGateway::class);
        $this->app->bind(SwitchQueueGateway::class, CrossbarSwitchQueueGateway::class);
        $this->app->bind(SwitchRecordingGateway::class, CrossbarSwitchRecordingGateway::class);
        $this->app->bind(SwitchServiceGateway::class, CrossbarSwitchServiceGateway::class);
        $this->app->bind(SwitchAgentGateway::class, CrossbarSwitchAgentGateway::class);
        $this->app->bind(SwitchTemporalRuleGateway::class, CrossbarSwitchTemporalRuleGateway::class);
        $this->app->bind(SwitchTemporalRuleSetGateway::class, CrossbarSwitchTemporalRuleSetGateway::class);
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

        $this->app->singleton(BlacklistResourceClient::class, fn ($app) => new BlacklistResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(AgentResourceClient::class, fn ($app) => new AgentResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(CallflowResourceClient::class, fn ($app) => new CallflowResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(ConferenceResourceClient::class, fn ($app) => new ConferenceResourceClient(
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

        $this->app->singleton(FaxBoxResourceClient::class, fn ($app) => new FaxBoxResourceClient(
            $app->make(SwitchClient::class),
            (int) config('switch.fax_page_size'),
        ));

        $this->app->singleton(FaxMessageResourceClient::class, fn ($app) => new FaxMessageResourceClient(
            $app->make(SwitchClient::class),
            (int) config('switch.fax_page_size'),
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

        $this->app->singleton(LineKeyResourceClient::class, fn ($app) => new LineKeyResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(PhoneNumberResourceClient::class, fn ($app) => new PhoneNumberResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(QueueResourceClient::class, fn ($app) => new QueueResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(RecordingResourceClient::class, fn ($app) => new RecordingResourceClient(
            $app->make(SwitchClient::class),
            (int) config('switch.recording_page_size'),
        ));

        $this->app->singleton(ServiceResourceClient::class, fn ($app) => new ServiceResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(UserResourceClient::class, fn ($app) => new UserResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(TemporalRuleResourceClient::class, fn ($app) => new TemporalRuleResourceClient(
            $app->make(SwitchClient::class),
        ));

        $this->app->singleton(TemporalRuleSetResourceClient::class, fn ($app) => new TemporalRuleSetResourceClient(
            $app->make(SwitchClient::class),
        ));
    }
}
