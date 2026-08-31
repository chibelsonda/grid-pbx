<?php

namespace Tests\Feature\Domains\Billing;

use App\Domains\Billing\Contracts\LegacyBillingReadOnlyGrantInspector;
use App\Domains\Billing\Gateways\LegacyGridPbxInvoiceDocumentGateway;
use App\Domains\Billing\Services\LegacyBillingDocumentPublicId;
use App\Domains\Billing\Services\LegacyBillingInvoiceDiagnosticService;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LegacyGridPbxInvoiceDocumentGatewayTest extends TestCase
{
    private const CONNECTION = 'legacy_billing_test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing_documents.invoices.provider' => 'legacy_gridpbx_mysql',
            'billing_documents.legacy_gridpbx.enabled' => true,
            'billing_documents.legacy_gridpbx.authority_confirmed' => true,
            'billing_documents.legacy_gridpbx.read_only_confirmed' => true,
            'billing_documents.legacy_gridpbx.connection' => self::CONNECTION,
            'database.connections.'.self::CONNECTION => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        $this->mock(
            LegacyBillingReadOnlyGrantInspector::class,
            fn (MockInterface $mock) => $mock->shouldReceive('isReadOnly')->andReturnTrue(),
        );
        DB::purge(self::CONNECTION);
        $schema = DB::connection(self::CONNECTION)->getSchemaBuilder();
        $schema->create('sw_account', function (Blueprint $table): void {
            $table->string('api_id')->primary();
            $table->unsignedBigInteger('crm_client_id');
            $table->boolean('switch_deleted')->default(false);
        });
        $schema->create('bill_invoice', function (Blueprint $table): void {
            $table->increments('bill_invoice_id');
            $table->unsignedBigInteger('crm_client_id');
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
        });
        $schema->create('bill_invoice_line', function (Blueprint $table): void {
            $table->increments('bill_invoice_line_id');
            $table->unsignedInteger('bill_invoice_id');
            $table->decimal('amount', 16, 2);
        });
        $schema->create('bill_invoice_payment', function (Blueprint $table): void {
            $table->increments('bill_invoice_payment_id');
            $table->unsignedInteger('bill_invoice_id');
            $table->decimal('amount', 16, 2);
        });
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_it_reads_only_the_mapped_clients_safe_invoice_summaries(): void
    {
        $database = DB::connection(self::CONNECTION);
        $database->table('sw_account')->insert([
            ['api_id' => 'switch-account-a', 'crm_client_id' => 10, 'switch_deleted' => false],
            ['api_id' => 'switch-account-b', 'crm_client_id' => 20, 'switch_deleted' => false],
        ]);
        $database->table('bill_invoice')->insert([
            ['bill_invoice_id' => 100, 'crm_client_id' => 10, 'invoice_number' => 'INV-100', 'invoice_date' => '2026-08-01', 'due_date' => '2026-08-31'],
            ['bill_invoice_id' => 200, 'crm_client_id' => 20, 'invoice_number' => 'PRIVATE-OTHER', 'invoice_date' => '2026-08-02', 'due_date' => '2026-09-01'],
        ]);
        $database->table('bill_invoice_line')->insert([
            ['bill_invoice_id' => 100, 'amount' => '100.25'],
            ['bill_invoice_id' => 100, 'amount' => '50.25'],
            ['bill_invoice_id' => 200, 'amount' => '999.00'],
        ]);
        $database->table('bill_invoice_payment')->insert([
            ['bill_invoice_id' => 100, 'amount' => '50.25'],
        ]);

        $result = $this->gateway()->forAccount(
            new SwitchAccount(['switch_account_id' => 'switch-account-a']),
            1,
        );

        $this->assertTrue($result->available);
        $this->assertTrue($result->authoritative);
        $this->assertSame('legacy_gridpbx_mysql', $result->source);
        $this->assertCount(1, $result->items);
        $this->assertTrue(Str::isUuid($result->items[0]['id']));
        $this->assertSame('INV-100', $result->items[0]['number']);
        $this->assertSame('150.50', $result->items[0]['total']);
        $this->assertSame('50.25', $result->items[0]['amount_paid']);
        $this->assertSame('100.25', $result->items[0]['amount_due']);
        $this->assertNull($result->items[0]['currency']);
        $this->assertFalse($result->items[0]['document_available']);
        $this->assertStringNotContainsString('PRIVATE-OTHER', json_encode($result->toArray()));
        $this->assertStringNotContainsString('bill_invoice_id', json_encode($result->toArray()));
    }

    public function test_it_fails_closed_when_the_account_has_no_active_legacy_mapping(): void
    {
        $result = $this->gateway()->forAccount(
            new SwitchAccount(['switch_account_id' => 'unmapped-account']),
            2,
        );

        $this->assertFalse($result->available);
        $this->assertTrue($result->authoritative);
        $this->assertSame('legacy_gridpbx_mysql', $result->source);
        $this->assertSame([], $result->items);
        $this->assertStringContainsString('no active client mapping', $result->guidance);
    }

    public function test_it_resolves_only_the_mapped_accounts_public_invoice_id(): void
    {
        $database = DB::connection(self::CONNECTION);
        $database->table('sw_account')->insert([
            ['api_id' => 'switch-account-a', 'crm_client_id' => 10, 'switch_deleted' => false],
            ['api_id' => 'switch-account-b', 'crm_client_id' => 20, 'switch_deleted' => false],
        ]);
        $database->table('bill_invoice')->insert([
            ['bill_invoice_id' => 100, 'crm_client_id' => 10, 'invoice_number' => 'INV-100', 'invoice_date' => '2026-08-01', 'due_date' => '2026-08-31'],
            ['bill_invoice_id' => 200, 'crm_client_id' => 20, 'invoice_number' => 'PRIVATE-OTHER', 'invoice_date' => '2026-08-02', 'due_date' => '2026-09-01'],
        ]);
        $database->table('bill_invoice_line')->insert([
            ['bill_invoice_id' => 100, 'amount' => '150.50'],
            ['bill_invoice_id' => 200, 'amount' => '999.00'],
        ]);
        $publicIds = $this->app->make(LegacyBillingDocumentPublicId::class);

        $invoice = $this->gateway()->findForAccount(
            new SwitchAccount(['switch_account_id' => 'switch-account-a']),
            $publicIds->invoice(100),
        );
        $foreignInvoice = $this->gateway()->findForAccount(
            new SwitchAccount(['switch_account_id' => 'switch-account-a']),
            $publicIds->invoice(200),
        );

        $this->assertNotNull($invoice);
        $this->assertSame('INV-100', $invoice->number);
        $this->assertSame('150.50', $invoice->total);
        $this->assertFalse($invoice->documentAvailable);
        $this->assertNull($foreignInvoice);
        $this->assertNull($this->gateway()->documentForAccount(
            new SwitchAccount(['switch_account_id' => 'switch-account-a']),
            $invoice,
        ));
    }

    public function test_it_refuses_invoice_queries_when_the_live_grant_is_not_read_only(): void
    {
        DB::connection(self::CONNECTION)->table('sw_account')->insert([
            'api_id' => 'switch-account-a',
            'crm_client_id' => 10,
            'switch_deleted' => false,
        ]);
        $grants = Mockery::mock(LegacyBillingReadOnlyGrantInspector::class);
        $grants->shouldReceive('isReadOnly')->once()->andReturnFalse();
        $diagnostics = new LegacyBillingInvoiceDiagnosticService(
            $this->app->make(ConnectionResolverInterface::class),
            $grants,
        );
        $gateway = new LegacyGridPbxInvoiceDocumentGateway(
            $this->app->make(ConnectionResolverInterface::class),
            $this->app->make(LegacyBillingDocumentPublicId::class),
            $diagnostics,
        );

        $result = $gateway->forAccount(
            new SwitchAccount(['switch_account_id' => 'switch-account-a']),
            1,
        );

        $this->assertFalse($result->available);
        $this->assertSame([], $result->items);
        $this->assertStringContainsString('strictly read-only', $result->guidance);
    }

    private function gateway(): LegacyGridPbxInvoiceDocumentGateway
    {
        return new LegacyGridPbxInvoiceDocumentGateway(
            $this->app->make(ConnectionResolverInterface::class),
            $this->app->make(LegacyBillingDocumentPublicId::class),
            $this->app->make(LegacyBillingInvoiceDiagnosticService::class),
        );
    }
}
