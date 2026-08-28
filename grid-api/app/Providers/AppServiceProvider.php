<?php

namespace App\Providers;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\CallDetailRecords\Policies\CallDetailRecordPolicy;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\CallRouting\Policies\CallflowPolicy;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Devices\Policies\DevicePolicy;
use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\Directories\Policies\DirectoryPolicy;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Extensions\Policies\ExtensionPolicy;
use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\Groups\Policies\GroupPolicy;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Media\Policies\MediaPolicy;
use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\Menus\Policies\MenuPolicy;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\Queues\Policies\QueuePolicy;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use App\Domains\Voicemail\Policies\VoicemailBoxPolicy;
use App\Domains\Voicemail\Policies\VoicemailMessagePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Gate::policy(SwitchCallflow::class, CallflowPolicy::class);
        Gate::policy(SwitchCallDetailRecord::class, CallDetailRecordPolicy::class);
        Gate::policy(SwitchDevice::class, DevicePolicy::class);
        Gate::policy(SwitchDirectory::class, DirectoryPolicy::class);
        Gate::policy(SwitchExtension::class, ExtensionPolicy::class);
        Gate::policy(SwitchMedia::class, MediaPolicy::class);
        Gate::policy(SwitchMenu::class, MenuPolicy::class);
        Gate::policy(SwitchGroup::class, GroupPolicy::class);
        Gate::policy(SwitchQueue::class, QueuePolicy::class);
        Gate::policy(SwitchVoicemailBox::class, VoicemailBoxPolicy::class);
        Gate::policy(SwitchVoicemailMessage::class, VoicemailMessagePolicy::class);
    }
}
