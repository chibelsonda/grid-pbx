<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\Dto\BillingDocumentContent;
use App\Domains\Billing\Dto\BillingDocumentSourceResult;
use App\Domains\Billing\Dto\BillingInvoice;
use App\Domains\Organizations\Models\SwitchAccount;

interface InvoiceDocumentGateway
{
    public function forAccount(
        SwitchAccount $account,
        int $reportedCount,
        int $limit = 25,
    ): BillingDocumentSourceResult;

    public function findForAccount(
        SwitchAccount $account,
        string $invoiceId,
    ): ?BillingInvoice;

    public function documentForAccount(
        SwitchAccount $account,
        BillingInvoice $invoice,
    ): ?BillingDocumentContent;
}
