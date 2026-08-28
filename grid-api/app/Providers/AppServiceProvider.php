<?php

namespace App\Providers;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\CallDetailRecords\Policies\CallDetailRecordPolicy;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\CallRouting\Policies\CallflowPolicy;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Devices\Policies\DevicePolicy;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Extensions\Policies\ExtensionPolicy;
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
        Gate::policy(SwitchExtension::class, ExtensionPolicy::class);
        Gate::policy(SwitchVoicemailBox::class, VoicemailBoxPolicy::class);
        Gate::policy(SwitchVoicemailMessage::class, VoicemailMessagePolicy::class);
    }
}
