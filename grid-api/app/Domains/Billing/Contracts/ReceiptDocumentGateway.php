<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\Dto\BillingDocumentContent;
use App\Domains\Billing\Dto\BillingDocumentSourceResult;
use App\Domains\Billing\Dto\BillingReceipt;
use App\Domains\Organizations\Models\SwitchAccount;

interface ReceiptDocumentGateway
{
    public function forAccount(
        SwitchAccount $account,
        int $limit = 25,
    ): BillingDocumentSourceResult;

    public function findForAccount(
        SwitchAccount $account,
        string $receiptId,
    ): ?BillingReceipt;

    public function documentForAccount(
        SwitchAccount $account,
        BillingReceipt $receipt,
    ): ?BillingDocumentContent;
}
