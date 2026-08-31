<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\InvoiceDocumentGateway;
use App\Domains\Billing\Dto\BillingInvoice;
use App\Domains\Billing\Enums\BillingDocumentType;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class BillingInvoiceService
{
    public function __construct(
        private InvoiceDocumentGateway $gateway,
        private BillingDocumentDownloadService $downloads,
    ) {}

    public function find(SwitchAccount $account, string $invoiceId): ?BillingInvoice
    {
        return $this->gateway->findForAccount($account, $invoiceId);
    }

    public function streamDocument(
        SwitchAccount $account,
        BillingInvoice $invoice,
        User $actor,
        ?string $ipAddress = null,
    ): ?StreamedResponse {
        if (! $invoice->documentAvailable) {
            return null;
        }

        $document = $this->gateway->documentForAccount($account, $invoice);

        return $document === null
            ? null
            : $this->downloads->streamPdf(
                $document,
                BillingDocumentType::Invoice,
                $invoice->id,
                $account,
                $actor,
                $ipAddress,
            );
    }
}
