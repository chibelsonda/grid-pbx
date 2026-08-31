<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\InvoiceDocumentGateway;
use App\Domains\Billing\Contracts\ReceiptDocumentGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Services\Models\SwitchServiceSummary;

final class BillingDocumentOverviewService
{
    public function __construct(
        private readonly InvoiceDocumentGateway $invoices,
        private readonly ReceiptDocumentGateway $receipts,
    ) {}

    /** @return array<string, mixed> */
    public function forAccount(
        SwitchAccount $account,
        SwitchServiceSummary $summary,
        int $limit = 25,
    ): array {
        $invoices = $this->invoices->forAccount($account, $summary->invoice_count, $limit);
        $receipts = $this->receipts->forAccount($account, $limit);
        $confirmations = PaymentAttempt::query()
            ->with('sourceAttempt:payment_attempt_id,id')
            ->where('switch_account_id', $account->getKey())
            ->where('status', PaymentAttemptStatus::Succeeded->value)
            ->whereIn('operation', [
                PaymentOperation::Charge->value,
                PaymentOperation::Refund->value,
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('payment_attempt_id')
            ->limit(max(1, min(50, $limit)))
            ->get()
            ->map(fn (PaymentAttempt $attempt): array => [
                'id' => $attempt->id,
                'source_attempt_id' => $attempt->sourceAttempt?->id,
                'provider' => $attempt->provider,
                'operation' => $attempt->operation->value,
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
                'status' => $attempt->status->value,
                'completed_at' => $attempt->completed_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'invoices' => $invoices->toArray(),
            'receipts' => $receipts->toArray(),
            'payment_confirmations' => [
                'available' => true,
                'authoritative' => false,
                'source' => 'gridpbx_payment_attempts',
                'items' => $confirmations,
                'guidance' => 'These records confirm GridPBX payment operations only. They do not replace an invoice or provider-issued receipt.',
            ],
        ];
    }
}
