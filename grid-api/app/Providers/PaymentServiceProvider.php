<?php

namespace App\Providers;

use App\Domains\Payments\Contracts\PaymentChargeGateway;
use App\Domains\Payments\Contracts\PaymentProfileGateway;
use App\Domains\Payments\Contracts\PaymentProviderDiagnosticsGateway;
use App\Domains\Payments\Contracts\PaymentReversalGateway;
use App\Domains\Payments\Contracts\PaymentTransactionStatusGateway;
use App\Domains\Payments\Gateways\AuthorizeNetPaymentChargeGateway;
use App\Domains\Payments\Gateways\AuthorizeNetPaymentProfileGateway;
use App\Domains\Payments\Gateways\AuthorizeNetPaymentProviderDiagnosticsGateway;
use App\Domains\Payments\Gateways\AuthorizeNetPaymentReversalGateway;
use App\Domains\Payments\Gateways\AuthorizeNetPaymentTransactionStatusGateway;
use App\Domains\Payments\Gateways\UnavailablePaymentChargeGateway;
use App\Domains\Payments\Gateways\UnavailablePaymentProfileGateway;
use App\Domains\Payments\Gateways\UnavailablePaymentProviderDiagnosticsGateway;
use App\Domains\Payments\Gateways\UnavailablePaymentReversalGateway;
use App\Domains\Payments\Gateways\UnavailablePaymentTransactionStatusGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentChargeGateway::class, function ($app) {
            if (config('payments.provider') !== 'authorize_net') {
                return new UnavailablePaymentChargeGateway;
            }

            return $app->make(AuthorizeNetPaymentChargeGateway::class);
        });

        $this->app->singleton(PaymentProviderDiagnosticsGateway::class, function ($app) {
            if (config('payments.provider') !== 'authorize_net') {
                return new UnavailablePaymentProviderDiagnosticsGateway;
            }

            return $app->make(AuthorizeNetPaymentProviderDiagnosticsGateway::class);
        });

        $this->app->singleton(PaymentReversalGateway::class, function ($app) {
            if (config('payments.provider') !== 'authorize_net') {
                return new UnavailablePaymentReversalGateway;
            }

            return $app->make(AuthorizeNetPaymentReversalGateway::class);
        });

        $this->app->singleton(PaymentProfileGateway::class, function ($app) {
            if (config('payments.provider') !== 'authorize_net') {
                return new UnavailablePaymentProfileGateway;
            }

            return $app->make(AuthorizeNetPaymentProfileGateway::class);
        });

        $this->app->singleton(PaymentTransactionStatusGateway::class, function ($app) {
            if (config('payments.provider') !== 'authorize_net') {
                return new UnavailablePaymentTransactionStatusGateway;
            }

            return $app->make(AuthorizeNetPaymentTransactionStatusGateway::class);
        });
    }

    public function boot(): void
    {
        RateLimiter::for('payment-sandbox', function (Request $request): Limit {
            $key = implode('|', [
                (string) $request->user()?->getAuthIdentifier(),
                (string) $request->route('account'),
                (string) $request->ip(),
            ]);

            return Limit::perMinute(3)->by($key);
        });
    }
}
