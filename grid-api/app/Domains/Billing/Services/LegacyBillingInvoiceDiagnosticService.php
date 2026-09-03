<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\LegacyBillingReadOnlyGrantInspector;
use App\Domains\Billing\Dto\LegacyBillingInvoiceDiagnostic;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Throwable;

final readonly class LegacyBillingInvoiceDiagnosticService
{
    /** @var array<string, list<string>> */
    private const REQUIRED_SCHEMA = [
        'sw_account' => ['api_id', 'crm_client_id', 'switch_deleted'],
        'bill_invoice' => [
            'bill_invoice_id',
            'crm_client_id',
            'invoice_number',
            'invoice_date',
            'due_date',
        ],
        'bill_invoice_line' => ['bill_invoice_id', 'amount'],
        'bill_invoice_payment' => ['bill_invoice_id', 'amount'],
    ];

    public function __construct(
        private ConnectionResolverInterface $connections,
        private LegacyBillingReadOnlyGrantInspector $grantInspector,
    ) {}

    public function inspect(): LegacyBillingInvoiceDiagnostic
    {
        $providerSelected = config('billing_documents.invoices.provider') === 'legacy_gridpbx_mysql';
        $adapterEnabled = (bool) config('billing_documents.legacy_gridpbx.enabled', false);
        $authorityConfirmed = (bool) config(
            'billing_documents.legacy_gridpbx.authority_confirmed',
            false,
        );
        $readOnlyConfirmed = (bool) config(
            'billing_documents.legacy_gridpbx.read_only_confirmed',
            false,
        );
        $connectionConfigured = $this->connectionConfigured();

        $blocked = $this->blockedDiagnostic(
            $providerSelected,
            $adapterEnabled,
            $authorityConfirmed,
            $readOnlyConfirmed,
            $connectionConfigured,
        );

        if ($blocked !== null) {
            return $blocked;
        }

        try {
            $connection = $this->connections->connection($this->connectionName());
            $connection->getPdo();
        } catch (Throwable) {
            return $this->diagnostic(
                providerSelected: true,
                adapterEnabled: true,
                authorityConfirmed: true,
                readOnlyConfirmed: true,
                connectionConfigured: true,
                connectionAttempted: true,
                status: 'connection_failed',
                guidance: 'The legacy billing connection could not be verified. Check its server-side connection settings and network access.',
            );
        }

        try {
            $readOnlyGrantVerified = $this->grantInspector->isReadOnly($connection);
        } catch (Throwable) {
            return $this->diagnostic(
                providerSelected: true,
                adapterEnabled: true,
                authorityConfirmed: true,
                readOnlyConfirmed: true,
                connectionConfigured: true,
                connectionAttempted: true,
                connectionReady: true,
                status: 'grant_verification_failed',
                guidance: 'The database privileges could not be verified safely. Use a dedicated account with only SELECT and SHOW VIEW privileges.',
            );
        }

        if (! $readOnlyGrantVerified) {
            return $this->diagnostic(
                providerSelected: true,
                adapterEnabled: true,
                authorityConfirmed: true,
                readOnlyConfirmed: true,
                connectionConfigured: true,
                connectionAttempted: true,
                connectionReady: true,
                status: 'write_privileges_detected',
                guidance: 'The configured database account is not strictly read-only. Replace it with a dedicated account limited to SELECT and SHOW VIEW.',
            );
        }

        try {
            $schemaCompatible = $this->schemaCompatible($connection);
        } catch (Throwable) {
            return $this->diagnostic(
                providerSelected: true,
                adapterEnabled: true,
                authorityConfirmed: true,
                readOnlyConfirmed: true,
                connectionConfigured: true,
                connectionAttempted: true,
                connectionReady: true,
                readOnlyGrantVerified: true,
                status: 'schema_verification_failed',
                guidance: 'The required legacy billing schema could not be verified. Check metadata access without granting write privileges.',
            );
        }

        if (! $schemaCompatible) {
            return $this->diagnostic(
                providerSelected: true,
                adapterEnabled: true,
                authorityConfirmed: true,
                readOnlyConfirmed: true,
                connectionConfigured: true,
                connectionAttempted: true,
                connectionReady: true,
                readOnlyGrantVerified: true,
                status: 'schema_incompatible',
                guidance: 'The legacy billing schema is missing one or more required tables or columns. Keep invoice synchronization disabled until the contract is reviewed.',
            );
        }

        return $this->diagnostic(
            providerSelected: true,
            adapterEnabled: true,
            authorityConfirmed: true,
            readOnlyConfirmed: true,
            connectionConfigured: true,
            connectionAttempted: true,
            connectionReady: true,
            readOnlyGrantVerified: true,
            schemaCompatible: true,
            status: 'ready',
            guidance: 'The legacy invoice source passed its read-only connection, privilege, and schema checks.',
        );
    }

    private function connectionConfigured(): bool
    {
        $connection = config('database.connections.'.$this->connectionName(), []);

        if (! is_array($connection)) {
            return false;
        }

        $driver = $connection['driver'] ?? null;
        $database = $connection['database'] ?? null;

        if (! is_string($driver) || $driver === '' || ! is_string($database) || $database === '') {
            return false;
        }

        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return true;
        }

        $username = $connection['username'] ?? null;
        $host = $connection['host'] ?? null;
        $socket = $connection['unix_socket'] ?? null;

        return is_string($username)
            && $username !== ''
            && ((is_string($host) && $host !== '') || (is_string($socket) && $socket !== ''));
    }

    private function schemaCompatible(ConnectionInterface $connection): bool
    {
        $schema = $connection->getSchemaBuilder();

        foreach (self::REQUIRED_SCHEMA as $table => $columns) {
            if (! $schema->hasTable($table) || ! $schema->hasColumns($table, $columns)) {
                return false;
            }
        }

        return true;
    }

    private function blockedDiagnostic(
        bool $providerSelected,
        bool $adapterEnabled,
        bool $authorityConfirmed,
        bool $readOnlyConfirmed,
        bool $connectionConfigured,
    ): ?LegacyBillingInvoiceDiagnostic {
        $status = match (false) {
            $providerSelected => 'provider_not_selected',
            $adapterEnabled => 'adapter_disabled',
            $authorityConfirmed => 'authority_unconfirmed',
            $readOnlyConfirmed => 'read_only_unconfirmed',
            $connectionConfigured => 'connection_unconfigured',
            default => null,
        };

        if ($status === null) {
            return null;
        }

        $guidance = match ($status) {
            'provider_not_selected' => 'Select the legacy GridPBX invoice provider only after confirming it is the billing authority.',
            'adapter_disabled' => 'The legacy invoice adapter is disabled. Keep it disabled until authority and read-only access are confirmed.',
            'authority_unconfirmed' => 'Confirm that the legacy database is the authoritative invoice source before enabling reads.',
            'read_only_unconfirmed' => 'Confirm that the configured database account is dedicated and read-only before enabling reads.',
            default => 'Configure the legacy billing database connection on the server. Credentials are never included in diagnostic output.',
        };

        return $this->diagnostic(
            providerSelected: $providerSelected,
            adapterEnabled: $adapterEnabled,
            authorityConfirmed: $authorityConfirmed,
            readOnlyConfirmed: $readOnlyConfirmed,
            connectionConfigured: $connectionConfigured,
            status: $status,
            guidance: $guidance,
        );
    }

    private function connectionName(): string
    {
        return (string) config(
            'billing_documents.legacy_gridpbx.connection',
            'legacy_billing',
        );
    }

    private function diagnostic(
        bool $providerSelected,
        bool $adapterEnabled,
        bool $authorityConfirmed,
        bool $readOnlyConfirmed,
        bool $connectionConfigured,
        string $status,
        string $guidance,
        bool $connectionAttempted = false,
        bool $connectionReady = false,
        bool $readOnlyGrantVerified = false,
        bool $schemaCompatible = false,
    ): LegacyBillingInvoiceDiagnostic {
        return new LegacyBillingInvoiceDiagnostic(
            providerSelected: $providerSelected,
            adapterEnabled: $adapterEnabled,
            authorityConfirmed: $authorityConfirmed,
            readOnlyConfirmed: $readOnlyConfirmed,
            connectionConfigured: $connectionConfigured,
            connectionAttempted: $connectionAttempted,
            connectionReady: $connectionReady,
            readOnlyGrantVerified: $readOnlyGrantVerified,
            schemaCompatible: $schemaCompatible,
            status: $status,
            guidance: $guidance,
        );
    }
}
