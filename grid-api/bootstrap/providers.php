<?php

use App\Providers\AppServiceProvider;
use App\Providers\BillingDocumentServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\SwitchServiceProvider;

return [
    AppServiceProvider::class,
    BillingDocumentServiceProvider::class,
    PaymentServiceProvider::class,
    SwitchServiceProvider::class,
];
