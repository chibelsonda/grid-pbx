<?php

namespace App\Domains\Billing\Gateways;

use App\Domains\Billing\Contracts\InvoiceDocumentGateway;
use App\Domains\Billing\Dto\BillingDocumentContent;
use App\Domains\Billing\Dto\BillingDocumentSourceResult;
use App\Domains\Billing\Dto\BillingInvoice;
use App\Domains\Billing\Services\LegacyBillingDocumentPublicId;
use App\Domains\Billing\Services\LegacyBillingInvoiceDiagnosticService;
use App\Domains\Organizations\Models\SwitchAccount;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;

final readonly class LegacyGridPbxInvoiceDocumentGateway implements InvoiceDocumentGateway
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private LegacyBillingDocumentPublicId $publicIds,
        private LegacyBillingInvoiceDiagnosticService $diagnostics,
    ) {}

    public function forAccount(
        SwitchAccount $account,
        int $reportedCount,
        int $limit = 25,
    ): BillingDocumentSourceResult {
        $diagnostic = $this->diagnostics->inspect();

        if (! $diagnostic->ready()) {
            return new BillingDocumentSourceResult(
                available: false,
                authoritative: true,
                source: 'legacy_gridpbx_mysql',
                items: [],
                guidance: $diagnostic->guidance,
                reportedCount: max(0, $reportedCount),
            );
        }

        $connection = $this->connections->connection(
            (string) config('billing_documents.legacy_gridpbx.connection', 'legacy_billing'),
        );
        $clientId = $this->legacyClientId($connection, $account);

        if ($clientId === null) {
            return new BillingDocumentSourceResult(
                available: false,
                authoritative: true,
                source: 'legacy_gridpbx_mysql',
                items: [],
                guidance: 'The authoritative legacy billing database has no active client mapping for this Switch account. Verify the server-side account mapping; no fallback query was attempted.',
                reportedCount: max(0, $reportedCount),
            );
        }

        $items = $this->invoiceQuery($connection)
            ->where('invoice.crm_client_id', $clientId)
            ->orderByDesc('invoice.invoice_date')
            ->orderByDesc('invoice.bill_invoice_id')
            ->limit(max(1, min(50, $limit)))
            ->get($this->invoiceColumns($connection))
            ->map(fn (object $invoice): array => $this->invoice($invoice)->summary())
            ->values()
            ->all();

        return new BillingDocumentSourceResult(
            available: true,
            authoritative: true,
            source: 'legacy_gridpbx_mysql',
            items: $items,
            guidance: 'Invoice summaries are read from the confirmed legacy billing authority. Currency and binary documents remain unavailable because that contract is not present in the verified schema.',
            reportedCount: max(0, $reportedCount),
        );
    }

    public function findForAccount(
        SwitchAccount $account,
        string $invoiceId,
    ): ?BillingInvoice {
        if (! $this->diagnostics->inspect()->ready()) {
            return null;
        }

        $connection = $this->connections->connection(
            (string) config('billing_documents.legacy_gridpbx.connection', 'legacy_billing'),
        );
        $clientId = $this->legacyClientId($connection, $account);

        if ($clientId === null) {
            return null;
        }

        $lookupLimit = max(
            1,
            min(5000, (int) config('billing_documents.legacy_gridpbx.detail_lookup_limit', 500)),
        );
        $legacyInvoiceId = $connection->table('bill_invoice')
            ->where('crm_client_id', $clientId)
            ->orderByDesc('invoice_date')
            ->orderByDesc('bill_invoice_id')
            ->limit($lookupLimit)
            ->pluck('bill_invoice_id')
            ->first(fn (mixed $candidate): bool => hash_equals(
                $this->publicIds->invoice((int) $candidate),
                $invoiceId,
            ));

        if ($legacyInvoiceId === null) {
            return null;
        }

        $invoice = $this->invoiceQuery($connection)
            ->where('invoice.crm_client_id', $clientId)
            ->where('invoice.bill_invoice_id', (int) $legacyInvoiceId)
            ->first($this->invoiceColumns($connection));

        return $invoice === null ? null : $this->invoice($invoice);
    }

    public function documentForAccount(
        SwitchAccount $account,
        BillingInvoice $invoice,
    ): ?BillingDocumentContent {
        return null;
    }

    private function invoiceQuery(ConnectionInterface $connection): Builder
    {
        $lineTotals = $connection->table('bill_invoice_line')
            ->select('bill_invoice_id')
            ->selectRaw('SUM(amount) AS total_amount')
            ->groupBy('bill_invoice_id');
        $paymentTotals = $connection->table('bill_invoice_payment')
            ->select('bill_invoice_id')
            ->selectRaw('SUM(amount) AS amount_paid')
            ->groupBy('bill_invoice_id');

        return $connection->table('bill_invoice AS invoice')
            ->joinSub(
                $lineTotals,
                'line_totals',
                fn ($join) => $join->on(
                    'line_totals.bill_invoice_id',
                    '=',
                    'invoice.bill_invoice_id',
                ),
            )
            ->leftJoinSub(
                $paymentTotals,
                'payment_totals',
                fn ($join) => $join->on(
                    'payment_totals.bill_invoice_id',
                    '=',
                    'invoice.bill_invoice_id',
                ),
            );
    }

    /** @return array<int, mixed> */
    private function invoiceColumns(ConnectionInterface $connection): array
    {
        return [
            'invoice.bill_invoice_id',
            'invoice.invoice_number',
            'invoice.invoice_date',
            'invoice.due_date',
            'line_totals.total_amount',
            $connection->raw('COALESCE(payment_totals.amount_paid, 0) AS amount_paid'),
            $connection->raw('(line_totals.total_amount - COALESCE(payment_totals.amount_paid, 0)) AS amount_due'),
        ];
    }

    private function invoice(object $invoice): BillingInvoice
    {
        $amountDue = $this->decimal($invoice->amount_due);

        return new BillingInvoice(
            id: $this->publicIds->invoice((int) $invoice->bill_invoice_id),
            number: $this->nullableString($invoice->invoice_number),
            status: BigDecimal::of($amountDue)->compareTo(BigDecimal::zero()) <= 0
                ? 'paid'
                : 'open',
            currency: null,
            total: $this->decimal($invoice->total_amount),
            amountPaid: $this->decimal($invoice->amount_paid),
            amountDue: $amountDue,
            issuedAt: $this->nullableString($invoice->invoice_date),
            dueAt: $this->nullableString($invoice->due_date),
            authoritative: true,
            source: 'legacy_gridpbx_mysql',
            documentAvailable: false,
        );
    }

    private function legacyClientId(
        ConnectionInterface $connection,
        SwitchAccount $account,
    ): ?int {
        $value = $connection->table('sw_account')
            ->where('api_id', $account->switch_account_id)
            ->where('switch_deleted', 0)
            ->value('crm_client_id');

        return is_int($value) || ctype_digit((string) $value) ? (int) $value : null;
    }

    private function decimal(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
