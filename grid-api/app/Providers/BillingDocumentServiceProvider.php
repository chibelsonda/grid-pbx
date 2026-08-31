<?php

namespace App\Providers;

use App\Domains\Billing\Contracts\InvoiceDocumentGateway;
use App\Domains\Billing\Contracts\LegacyBillingReadOnlyGrantInspector;
use App\Domains\Billing\Contracts\ReceiptDocumentGateway;
use App\Domains\Billing\Gateways\LegacyGridPbxInvoiceDocumentGateway;
use App\Domains\Billing\Gateways\UnavailableInvoiceDocumentGateway;
use App\Domains\Billing\Gateways\UnavailableReceiptDocumentGateway;
use App\Domains\Billing\Services\MySqlLegacyBillingReadOnlyGrantInspector;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class BillingDocumentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            LegacyBillingReadOnlyGrantInspector::class,
            MySqlLegacyBillingReadOnlyGrantInspector::class,
        );
        $this->app->singleton(
            InvoiceDocumentGateway::class,
            fn ($app): InvoiceDocumentGateway => $this->invoiceGateway($app),
        );
        $this->app->singleton(
            ReceiptDocumentGateway::class,
            fn (): ReceiptDocumentGateway => new UnavailableReceiptDocumentGateway(
                $this->configuredProvider('billing_documents.receipts.provider') === 'unconfigured'
                    ? 'unconfigured'
                    : 'unsupported',
            ),
        );
    }

    public function boot(): void
    {
        RateLimiter::for('billing-documents', function (Request $request): Limit {
            $key = implode('|', [
                (string) $request->user()?->getAuthIdentifier(),
                (string) $request->route('account'),
                (string) $request->ip(),
            ]);

            return Limit::perMinute(30)->by($key);
        });
    }

    private function invoiceGateway(mixed $app): InvoiceDocumentGateway
    {
        $provider = $this->configuredProvider('billing_documents.invoices.provider');

        if ($provider === 'unconfigured') {
            return new UnavailableInvoiceDocumentGateway;
        }

        if ($provider !== 'legacy_gridpbx_mysql') {
            return new UnavailableInvoiceDocumentGateway('unsupported');
        }

        if (! config('billing_documents.legacy_gridpbx.enabled')
            || ! config('billing_documents.legacy_gridpbx.authority_confirmed')
            || ! config('billing_documents.legacy_gridpbx.read_only_confirmed')) {
            return new UnavailableInvoiceDocumentGateway('pending_confirmation');
        }

        return $app->make(LegacyGridPbxInvoiceDocumentGateway::class);
    }

    private function configuredProvider(string $key): string
    {
        $provider = config($key, 'unconfigured');

        return is_string($provider) && $provider !== '' ? $provider : 'unconfigured';
    }
}
