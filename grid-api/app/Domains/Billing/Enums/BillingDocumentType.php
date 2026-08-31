<?php

namespace App\Domains\Billing\Enums;

enum BillingDocumentType: string
{
    case Invoice = 'billing_invoice';
    case Receipt = 'billing_receipt';

    public function downloadAction(): string
    {
        return match ($this) {
            self::Invoice => 'billing_invoice.downloaded',
            self::Receipt => 'billing_receipt.downloaded',
        };
    }

    public function filenamePrefix(): string
    {
        return match ($this) {
            self::Invoice => 'invoice',
            self::Receipt => 'receipt',
        };
    }
}
