<?php

use App\Providers\AppServiceProvider;
use App\Providers\BillingDocumentServiceProvider;
use App\Providers\DashboardServiceProvider;
use App\Providers\GlobalSearchServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\SwitchServiceProvider;

return [
    AppServiceProvider::class,
    BillingDocumentServiceProvider::class,
    DashboardServiceProvider::class,
    GlobalSearchServiceProvider::class,
    PaymentServiceProvider::class,
    SwitchServiceProvider::class,
];
