<?php

namespace App\Domains\Billing\Services;

final class LegacyBillingDocumentPublicId
{
    public function invoice(int $legacyInvoiceId): string
    {
        $hex = substr(hash_hmac(
            'sha256',
            "legacy-gridpbx-invoice:{$legacyInvoiceId}",
            (string) config('app.key'),
        ), 0, 32);
        $hex[12] = '4';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
