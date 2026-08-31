<?php

namespace App\Domains\Billing\Gateways;

use App\Domains\Billing\Contracts\InvoiceDocumentGateway;
use App\Domains\Billing\Dto\BillingDocumentContent;
use App\Domains\Billing\Dto\BillingDocumentSourceResult;
use App\Domains\Billing\Dto\BillingInvoice;
use App\Domains\Organizations\Models\SwitchAccount;

final readonly class UnavailableInvoiceDocumentGateway implements InvoiceDocumentGateway
{
    public function __construct(private string $reason = 'unconfigured') {}

    public function forAccount(
        SwitchAccount $account,
        int $reportedCount,
        int $limit = 25,
    ): BillingDocumentSourceResult {
        return new BillingDocumentSourceResult(
            available: false,
            authoritative: false,
            source: $this->reason === 'unconfigured' ? 'unconfigured' : $this->reason,
            items: [],
            guidance: match ($this->reason) {
                'pending_confirmation' => 'The legacy invoice adapter remains disabled until its authority and read-only database credentials are explicitly confirmed.',
                'unsupported' => 'The configured invoice provider has no installed GridPBX adapter. No document request was attempted.',
                default => 'Switch reports invoice-group counts but does not expose authoritative invoice documents through the verified billing endpoints. Configure an approved invoice source before documents are shown.',
            },
            reportedCount: max(0, $reportedCount),
        );
    }

    public function findForAccount(SwitchAccount $account, string $invoiceId): ?BillingInvoice
    {
        return null;
    }

    public function documentForAccount(
        SwitchAccount $account,
        BillingInvoice $invoice,
    ): ?BillingDocumentContent {
        return null;
    }
}
