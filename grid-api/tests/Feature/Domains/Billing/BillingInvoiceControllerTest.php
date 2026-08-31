<?php

namespace Tests\Feature\Domains\Billing;

use App\Domains\Billing\Contracts\InvoiceDocumentGateway;
use App\Domains\Billing\Dto\BillingDocumentContent;
use App\Domains\Billing\Dto\BillingDocumentSourceResult;
use App\Domains\Billing\Dto\BillingInvoice;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BillingInvoiceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_account_administrator_views_provider_neutral_invoice_detail(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $invoice = $this->invoice(documentAvailable: false);
        $this->app->instance(
            InvoiceDocumentGateway::class,
            new FakeInvoiceDocumentGateway($invoice),
        );

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/billing/invoices/{$invoice->id}",
        );

        $response->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonPath('data.number', 'INV-2026-100')
            ->assertJsonPath('data.authoritative', true)
            ->assertJsonPath('data.line_items.available', false)
            ->assertJsonPath('data.document.available', false)
            ->assertJsonMissingPath('data.provider_reference')
            ->assertJsonMissingPath('data.legacy_invoice_id')
            ->assertJsonMissing(['provider-secret', 'private-database-id']);
    }

    public function test_returns_404_for_an_invoice_outside_the_selected_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $otherOrganization = Organization::factory()->create();
        $otherAccount = SwitchAccount::factory()->for($otherOrganization)->create();
        $invoice = $this->invoice(documentAvailable: false);
        $this->app->instance(
            InvoiceDocumentGateway::class,
            new FakeInvoiceDocumentGateway($invoice),
        );

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$otherAccount->id}/billing/invoices/{$invoice->id}")
            ->assertNotFound();

        $this->assertNotSame($account->id, $otherAccount->id);
    }

    public function test_returns_403_when_role_cannot_view_billing(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountOperator);
        $invoice = $this->invoice(documentAvailable: false);
        $this->app->instance(
            InvoiceDocumentGateway::class,
            new FakeInvoiceDocumentGateway($invoice),
        );

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/billing/invoices/{$invoice->id}")
            ->assertForbidden();
    }

    public function test_returns_401_when_invoice_request_is_unauthenticated(): void
    {
        $invoice = $this->invoice(documentAvailable: false);

        $this->getJson("/api/v1/accounts/{$invoice->id}/billing/invoices/{$invoice->id}")
            ->assertUnauthorized();
    }

    public function test_returns_404_when_provider_does_not_supply_the_invoice(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $unknownInvoiceId = '28ef89dc-f873-44af-9d31-ad68d334d360';
        $this->app->instance(
            InvoiceDocumentGateway::class,
            new FakeInvoiceDocumentGateway(null),
        );

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/billing/invoices/{$unknownInvoiceId}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Invoice is not available.');
    }

    public function test_streams_safe_pdf_and_audits_authorized_download(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $invoice = $this->invoice(documentAvailable: true);
        $document = new BillingDocumentContent(
            contentType: 'application/pdf',
            contentLength: 8,
            stream: static function (): void {
                echo '%PDF-1.7';
            },
        );
        $this->app->instance(
            InvoiceDocumentGateway::class,
            new FakeInvoiceDocumentGateway($invoice, $document),
        );

        $response = $this->actingAs($user)->get(
            "/api/v1/accounts/{$account->id}/billing/invoices/{$invoice->id}/document",
        );

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringStartsWith(
            'attachment; filename="invoice-',
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertSame('%PDF-1.7', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'switch_account_id' => $account->getKey(),
            'action' => 'billing_invoice.downloaded',
            'resource_type' => 'billing_invoice',
            'resource_id' => $invoice->id,
            'outcome' => 'succeeded',
        ]);
    }

    public function test_returns_404_and_does_not_audit_unsafe_document_content(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $invoice = $this->invoice(documentAvailable: true);
        $document = new BillingDocumentContent(
            contentType: 'text/html',
            contentLength: 31,
            stream: static function (): void {
                echo '<script>provider-secret</script>';
            },
        );
        $this->app->instance(
            InvoiceDocumentGateway::class,
            new FakeInvoiceDocumentGateway($invoice, $document),
        );

        $this->actingAs($user)
            ->get("/api/v1/accounts/{$account->id}/billing/invoices/{$invoice->id}/document")
            ->assertNotFound();

        $this->assertDatabaseMissing('audit_logs', [
            'switch_account_id' => $account->getKey(),
            'action' => 'billing_invoice.downloaded',
        ]);
    }

    public function test_returns_404_and_does_not_audit_oversized_document_content(): void
    {
        config(['billing_documents.downloads.maximum_bytes' => 4]);
        [$user, $account] = $this->accessibleAccount();
        $invoice = $this->invoice(documentAvailable: true);
        $document = new BillingDocumentContent(
            contentType: 'application/pdf',
            contentLength: 8,
            stream: static function (): void {
                echo '%PDF-1.7';
            },
        );
        $this->app->instance(
            InvoiceDocumentGateway::class,
            new FakeInvoiceDocumentGateway($invoice, $document),
        );

        $this->actingAs($user)
            ->get("/api/v1/accounts/{$account->id}/billing/invoices/{$invoice->id}/document")
            ->assertNotFound();

        $this->assertDatabaseMissing('audit_logs', [
            'switch_account_id' => $account->getKey(),
            'action' => 'billing_invoice.downloaded',
        ]);
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(
        OrganizationRole $role = OrganizationRole::AccountAdministrator,
    ): array {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }

    private function invoice(bool $documentAvailable): BillingInvoice
    {
        return new BillingInvoice(
            id: '96d7161d-438d-48fc-a69f-03d68f6f4f51',
            number: 'INV-2026-100',
            status: 'open',
            currency: 'USD',
            total: '150.50',
            amountPaid: '50.25',
            amountDue: '100.25',
            issuedAt: '2026-08-01',
            dueAt: '2026-08-31',
            authoritative: true,
            source: 'test_authority',
            documentAvailable: $documentAvailable,
            documentContentType: $documentAvailable ? 'application/pdf' : null,
        );
    }
}

final readonly class FakeInvoiceDocumentGateway implements InvoiceDocumentGateway
{
    public function __construct(
        private ?BillingInvoice $invoice,
        private ?BillingDocumentContent $document = null,
    ) {}

    public function forAccount(
        SwitchAccount $account,
        int $reportedCount,
        int $limit = 25,
    ): BillingDocumentSourceResult {
        return new BillingDocumentSourceResult(
            available: $this->invoice !== null,
            authoritative: true,
            source: 'test_authority',
            items: $this->invoice === null ? [] : [$this->invoice->summary()],
            guidance: 'Test authority.',
            reportedCount: $reportedCount,
        );
    }

    public function findForAccount(SwitchAccount $account, string $invoiceId): ?BillingInvoice
    {
        return $this->invoice?->id === $invoiceId ? $this->invoice : null;
    }

    public function documentForAccount(
        SwitchAccount $account,
        BillingInvoice $invoice,
    ): ?BillingDocumentContent {
        return $this->document;
    }
}
