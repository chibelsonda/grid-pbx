<?php

namespace App\Domains\Billing\Gateways;

use App\Domains\Billing\Contracts\ReceiptDocumentGateway;
use App\Domains\Billing\Dto\BillingDocumentContent;
use App\Domains\Billing\Dto\BillingDocumentSourceResult;
use App\Domains\Billing\Dto\BillingReceipt;
use App\Domains\Organizations\Models\SwitchAccount;

final readonly class UnavailableReceiptDocumentGateway implements ReceiptDocumentGateway
{
    public function __construct(private string $reason = 'unconfigured') {}

    public function forAccount(
        SwitchAccount $account,
        int $limit = 25,
    ): BillingDocumentSourceResult {
        return new BillingDocumentSourceResult(
            available: false,
            authoritative: false,
            source: $this->reason === 'unconfigured' ? 'unconfigured' : $this->reason,
            items: [],
            guidance: $this->reason === 'unsupported'
                ? 'The configured receipt provider has no installed GridPBX adapter. No document request was attempted.'
                : 'A provider receipt contract has not been approved. GridPBX payment confirmations are operational records and are not presented as legal or tax receipts.',
        );
    }

    public function findForAccount(SwitchAccount $account, string $receiptId): ?BillingReceipt
    {
        return null;
    }

    public function documentForAccount(
        SwitchAccount $account,
        BillingReceipt $receipt,
    ): ?BillingDocumentContent {
        return null;
    }
}
