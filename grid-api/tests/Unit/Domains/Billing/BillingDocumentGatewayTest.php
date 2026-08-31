<?php

namespace Tests\Unit\Domains\Billing;

use App\Domains\Billing\Gateways\UnavailableInvoiceDocumentGateway;
use App\Domains\Billing\Gateways\UnavailableReceiptDocumentGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use PHPUnit\Framework\TestCase;

class BillingDocumentGatewayTest extends TestCase
{
    public function test_unconfigured_document_gateways_fail_closed(): void
    {
        $account = new SwitchAccount;
        $invoiceGateway = new UnavailableInvoiceDocumentGateway;
        $invoices = $invoiceGateway->forAccount($account, 3);
        $receipts = (new UnavailableReceiptDocumentGateway)->forAccount($account);

        $this->assertFalse($invoices->available);
        $this->assertFalse($invoices->authoritative);
        $this->assertSame('unconfigured', $invoices->source);
        $this->assertSame(3, $invoices->reportedCount);
        $this->assertSame([], $invoices->items);
        $this->assertNull($invoiceGateway->findForAccount($account, 'missing'));
        $this->assertFalse($receipts->available);
        $this->assertFalse($receipts->authoritative);
        $this->assertSame('unconfigured', $receipts->source);
        $this->assertSame([], $receipts->items);
        $this->assertNull(
            (new UnavailableReceiptDocumentGateway)->findForAccount($account, 'missing'),
        );
    }

    public function test_unknown_configured_document_gateways_remain_unavailable(): void
    {
        $account = new SwitchAccount;
        $invoices = (new UnavailableInvoiceDocumentGateway('unsupported'))->forAccount($account, 1);
        $receipts = (new UnavailableReceiptDocumentGateway('unsupported'))->forAccount($account);

        $this->assertFalse($invoices->available);
        $this->assertSame('unsupported', $invoices->source);
        $this->assertStringContainsString('no installed GridPBX adapter', $invoices->guidance);
        $this->assertFalse($receipts->available);
        $this->assertSame('unsupported', $receipts->source);
        $this->assertStringContainsString('no installed GridPBX adapter', $receipts->guidance);
    }
}
