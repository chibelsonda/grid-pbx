<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\ReceiptDocumentGateway;
use App\Domains\Billing\Dto\BillingReceipt;
use App\Domains\Billing\Enums\BillingDocumentType;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class BillingReceiptService
{
    public function __construct(
        private ReceiptDocumentGateway $gateway,
        private BillingDocumentDownloadService $downloads,
    ) {}

    public function find(SwitchAccount $account, string $receiptId): ?BillingReceipt
    {
        return $this->gateway->findForAccount($account, $receiptId);
    }

    public function streamDocument(
        SwitchAccount $account,
        BillingReceipt $receipt,
        User $actor,
        ?string $ipAddress = null,
    ): ?StreamedResponse {
        if (! $receipt->documentAvailable) {
            return null;
        }

        $document = $this->gateway->documentForAccount($account, $receipt);

        return $document === null
            ? null
            : $this->downloads->streamPdf(
                $document,
                BillingDocumentType::Receipt,
                $receipt->id,
                $account,
                $actor,
                $ipAddress,
            );
    }
}
