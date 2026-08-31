<?php

namespace App\Console\Commands;

use App\Domains\Billing\Services\LegacyBillingInvoiceDiagnosticService;
use Illuminate\Console\Command;

final class VerifyLegacyBillingInvoicesCommand extends Command
{
    protected $signature = 'billing:legacy-invoices:verify {--json : Return machine-readable safe output}';

    protected $description = 'Safely verify the read-only legacy invoice connection and schema';

    public function handle(LegacyBillingInvoiceDiagnosticService $diagnostics): int
    {
        $diagnostic = $diagnostics->inspect();

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $diagnostic->toSafeArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));
        } else {
            $this->table(
                ['Provider', 'Enabled', 'Authority', 'Read only', 'Connection', 'Privileges', 'Schema', 'Status'],
                [[
                    $diagnostic->providerSelected ? 'selected' : 'not selected',
                    $diagnostic->adapterEnabled ? 'yes' : 'no',
                    $diagnostic->authorityConfirmed ? 'confirmed' : 'unconfirmed',
                    $diagnostic->readOnlyConfirmed ? 'confirmed' : 'unconfirmed',
                    $diagnostic->connectionReady ? 'ready' : 'not verified',
                    $diagnostic->readOnlyGrantVerified ? 'read only' : 'not verified',
                    $diagnostic->schemaCompatible ? 'compatible' : 'not verified',
                    $diagnostic->status,
                ]],
            );
            $this->line($diagnostic->guidance);
        }

        return $diagnostic->ready() ? self::SUCCESS : self::FAILURE;
    }
}
