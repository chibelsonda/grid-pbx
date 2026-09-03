<?php

use App\Providers\AppServiceProvider;
use App\Providers\BillingDocumentServiceProvider;
use App\Providers\CallRoutingServiceProvider;
use App\Providers\DashboardServiceProvider;
use App\Providers\GlobalSearchServiceProvider;
use App\Providers\IdentityAccessServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\SwitchServiceProvider;

return [
    AppServiceProvider::class,
    BillingDocumentServiceProvider::class,
    CallRoutingServiceProvider::class,
    DashboardServiceProvider::class,
    GlobalSearchServiceProvider::class,
    IdentityAccessServiceProvider::class,
    PaymentServiceProvider::class,
    SwitchServiceProvider::class,
];
