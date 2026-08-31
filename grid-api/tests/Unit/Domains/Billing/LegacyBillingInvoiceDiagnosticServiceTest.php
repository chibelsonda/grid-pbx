<?php

namespace Tests\Unit\Domains\Billing;

use App\Domains\Billing\Contracts\LegacyBillingReadOnlyGrantInspector;
use App\Domains\Billing\Services\LegacyBillingInvoiceDiagnosticService;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class LegacyBillingInvoiceDiagnosticServiceTest extends TestCase
{
    private const CONNECTION = 'legacy_billing_diagnostic_test';

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_it_fails_closed_without_attempting_a_connection_when_gates_are_disabled(): void
    {
        config([
            'billing_documents.invoices.provider' => 'unconfigured',
            'billing_documents.legacy_gridpbx.enabled' => false,
            'billing_documents.legacy_gridpbx.authority_confirmed' => false,
            'billing_documents.legacy_gridpbx.read_only_confirmed' => false,
        ]);
        $connections = Mockery::mock(ConnectionResolverInterface::class);
        $connections->shouldNotReceive('connection');
        $grants = Mockery::mock(LegacyBillingReadOnlyGrantInspector::class);
        $grants->shouldNotReceive('isReadOnly');

        $diagnostic = (new LegacyBillingInvoiceDiagnosticService($connections, $grants))->inspect();

        $this->assertSame('provider_not_selected', $diagnostic->status);
        $this->assertFalse($diagnostic->connectionAttempted);
        $this->assertFalse($diagnostic->ready());
    }

    public function test_it_reports_ready_only_after_connection_grant_and_schema_checks_pass(): void
    {
        $this->configureSqliteConnection();
        $this->createRequiredSchema();
        $grants = Mockery::mock(LegacyBillingReadOnlyGrantInspector::class);
        $grants->shouldReceive('isReadOnly')->once()->andReturnTrue();

        $diagnostic = $this->service($grants)->inspect();

        $this->assertTrue($diagnostic->ready());
        $this->assertTrue($diagnostic->connectionAttempted);
        $this->assertTrue($diagnostic->connectionReady);
        $this->assertTrue($diagnostic->readOnlyGrantVerified);
        $this->assertTrue($diagnostic->schemaCompatible);
        $this->assertSame('ready', $diagnostic->status);
    }

    public function test_it_rejects_an_incomplete_schema_without_exposing_table_details(): void
    {
        $this->configureSqliteConnection();
        DB::connection(self::CONNECTION)->getSchemaBuilder()->create(
            'sw_account',
            function (Blueprint $table): void {
                $table->string('api_id');
            },
        );
        $grants = Mockery::mock(LegacyBillingReadOnlyGrantInspector::class);
        $grants->shouldReceive('isReadOnly')->once()->andReturnTrue();

        $diagnostic = $this->service($grants)->inspect();
        $safeOutput = json_encode($diagnostic->toSafeArray(), JSON_THROW_ON_ERROR);

        $this->assertSame('schema_incompatible', $diagnostic->status);
        $this->assertFalse($diagnostic->schemaCompatible);
        $this->assertStringNotContainsString('sw_account', $safeOutput);
        $this->assertStringNotContainsString('bill_invoice', $safeOutput);
    }

    public function test_it_sanitizes_connection_exceptions(): void
    {
        $this->configureGatedConnection();
        $connections = Mockery::mock(ConnectionResolverInterface::class);
        $connections->shouldReceive('connection')
            ->once()
            ->andThrow(new RuntimeException('SQLSTATE secret-host password=private'));
        $grants = Mockery::mock(LegacyBillingReadOnlyGrantInspector::class);

        $diagnostic = (new LegacyBillingInvoiceDiagnosticService($connections, $grants))->inspect();
        $safeOutput = json_encode($diagnostic->toSafeArray(), JSON_THROW_ON_ERROR);

        $this->assertSame('connection_failed', $diagnostic->status);
        $this->assertStringNotContainsString('SQLSTATE', $safeOutput);
        $this->assertStringNotContainsString('secret-host', $safeOutput);
        $this->assertStringNotContainsString('private', $safeOutput);
    }

    private function configureSqliteConnection(): void
    {
        $this->configureGatedConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge(self::CONNECTION);
    }

    /** @param array<string, mixed>|null $connection */
    private function configureGatedConnection(?array $connection = null): void
    {
        config([
            'billing_documents.invoices.provider' => 'legacy_gridpbx_mysql',
            'billing_documents.legacy_gridpbx.enabled' => true,
            'billing_documents.legacy_gridpbx.authority_confirmed' => true,
            'billing_documents.legacy_gridpbx.read_only_confirmed' => true,
            'billing_documents.legacy_gridpbx.connection' => self::CONNECTION,
            'database.connections.'.self::CONNECTION => $connection ?? [
                'driver' => 'mysql',
                'host' => 'configured',
                'database' => 'configured',
                'username' => 'configured',
            ],
        ]);
    }

    private function createRequiredSchema(): void
    {
        $schema = DB::connection(self::CONNECTION)->getSchemaBuilder();
        $schema->create('sw_account', function (Blueprint $table): void {
            $table->string('api_id');
            $table->unsignedBigInteger('crm_client_id');
            $table->boolean('switch_deleted');
        });
        $schema->create('bill_invoice', function (Blueprint $table): void {
            $table->increments('bill_invoice_id');
            $table->unsignedBigInteger('crm_client_id');
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
        });
        $schema->create('bill_invoice_line', function (Blueprint $table): void {
            $table->unsignedInteger('bill_invoice_id');
            $table->decimal('amount');
        });
        $schema->create('bill_invoice_payment', function (Blueprint $table): void {
            $table->unsignedInteger('bill_invoice_id');
            $table->decimal('amount');
        });
    }

    private function service(
        LegacyBillingReadOnlyGrantInspector&MockInterface $grants,
    ): LegacyBillingInvoiceDiagnosticService {
        return new LegacyBillingInvoiceDiagnosticService(
            $this->app->make(ConnectionResolverInterface::class),
            $grants,
        );
    }
}
