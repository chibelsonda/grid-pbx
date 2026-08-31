<?php

namespace Tests\Feature\Domains\Billing;

use App\Domains\Billing\Contracts\ReceiptDocumentGateway;
use App\Domains\Billing\Dto\BillingDocumentContent;
use App\Domains\Billing\Dto\BillingDocumentSourceResult;
use App\Domains\Billing\Dto\BillingReceipt;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BillingReceiptControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_account_administrator_views_provider_neutral_receipt_detail(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $receipt = $this->receipt(documentAvailable: false);
        $this->app->instance(
            ReceiptDocumentGateway::class,
            new FakeReceiptDocumentGateway($receipt),
        );

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/billing/receipts/{$receipt->id}",
        );

        $response->assertOk()
            ->assertJsonPath('data.id', $receipt->id)
            ->assertJsonPath('data.number', 'RCT-2026-100')
            ->assertJsonPath('data.amount', '50.25')
            ->assertJsonPath('data.authoritative', true)
            ->assertJsonPath('data.document.available', false)
            ->assertJsonMissingPath('data.provider_reference')
            ->assertJsonMissingPath('data.payment_profile_id')
            ->assertJsonMissing(['provider-secret', 'private-database-id']);
    }

    public function test_returns_404_for_a_receipt_outside_the_selected_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $otherOrganization = Organization::factory()->create();
        $otherAccount = SwitchAccount::factory()->for($otherOrganization)->create();
        $receipt = $this->receipt(documentAvailable: false);
        $this->app->instance(
            ReceiptDocumentGateway::class,
            new FakeReceiptDocumentGateway($receipt),
        );

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$otherAccount->id}/billing/receipts/{$receipt->id}")
            ->assertNotFound();

        $this->assertNotSame($account->id, $otherAccount->id);
    }

    public function test_returns_403_when_role_cannot_view_receipts(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountOperator);
        $receipt = $this->receipt(documentAvailable: false);
        $this->app->instance(
            ReceiptDocumentGateway::class,
            new FakeReceiptDocumentGateway($receipt),
        );

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/billing/receipts/{$receipt->id}")
            ->assertForbidden();
    }

    public function test_returns_401_when_receipt_request_is_unauthenticated(): void
    {
        $receipt = $this->receipt(documentAvailable: false);

        $this->getJson("/api/v1/accounts/{$receipt->id}/billing/receipts/{$receipt->id}")
            ->assertUnauthorized();
    }

    public function test_returns_404_when_receipt_public_id_is_not_a_uuid(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/billing/receipts/private-provider-id")
            ->assertNotFound();
    }

    public function test_returns_404_when_provider_does_not_supply_the_receipt(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $unknownReceiptId = '8b6a808f-40bf-47d5-8a50-492568aa2d2c';
        $this->app->instance(
            ReceiptDocumentGateway::class,
            new FakeReceiptDocumentGateway(null),
        );

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/billing/receipts/{$unknownReceiptId}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Receipt is not available.');
    }

    public function test_streams_safe_pdf_and_audits_authorized_receipt_download(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $receipt = $this->receipt(documentAvailable: true);
        $document = new BillingDocumentContent(
            contentType: 'application/pdf',
            contentLength: 8,
            stream: static function (): void {
                echo '%PDF-1.7';
            },
        );
        $this->app->instance(
            ReceiptDocumentGateway::class,
            new FakeReceiptDocumentGateway($receipt, $document),
        );

        $response = $this->actingAs($user)->get(
            "/api/v1/accounts/{$account->id}/billing/receipts/{$receipt->id}/document",
        );

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringStartsWith(
            'attachment; filename="receipt-',
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertSame('%PDF-1.7', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'switch_account_id' => $account->getKey(),
            'action' => 'billing_receipt.downloaded',
            'resource_type' => 'billing_receipt',
            'resource_id' => $receipt->id,
            'outcome' => 'succeeded',
        ]);
    }

    public function test_returns_404_without_an_audit_when_receipt_document_is_unavailable(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $receipt = $this->receipt(documentAvailable: true);
        $this->app->instance(
            ReceiptDocumentGateway::class,
            new FakeReceiptDocumentGateway($receipt),
        );

        $this->actingAs($user)
            ->get("/api/v1/accounts/{$account->id}/billing/receipts/{$receipt->id}/document")
            ->assertNotFound();

        $this->assertDatabaseMissing('audit_logs', [
            'switch_account_id' => $account->getKey(),
            'action' => 'billing_receipt.downloaded',
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

    private function receipt(bool $documentAvailable): BillingReceipt
    {
        return new BillingReceipt(
            id: '6eb271ad-d3a0-474a-abce-7af6e703de31',
            number: 'RCT-2026-100',
            status: 'settled',
            currency: 'USD',
            amount: '50.25',
            paidAt: '2026-08-15T12:00:00Z',
            authoritative: true,
            source: 'test_authority',
            documentAvailable: $documentAvailable,
            documentContentType: $documentAvailable ? 'application/pdf' : null,
        );
    }
}

final readonly class FakeReceiptDocumentGateway implements ReceiptDocumentGateway
{
    public function __construct(
        private ?BillingReceipt $receipt,
        private ?BillingDocumentContent $document = null,
    ) {}

    public function forAccount(
        SwitchAccount $account,
        int $limit = 25,
    ): BillingDocumentSourceResult {
        return new BillingDocumentSourceResult(
            available: $this->receipt !== null,
            authoritative: true,
            source: 'test_authority',
            items: $this->receipt === null ? [] : [$this->receipt->summary()],
            guidance: 'Test authority.',
        );
    }

    public function findForAccount(SwitchAccount $account, string $receiptId): ?BillingReceipt
    {
        return $this->receipt?->id === $receiptId ? $this->receipt : null;
    }

    public function documentForAccount(
        SwitchAccount $account,
        BillingReceipt $receipt,
    ): ?BillingDocumentContent {
        return $this->document;
    }
}
