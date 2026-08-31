<?php

namespace App\Console\Commands;

use App\Domains\Payments\Contracts\PaymentProviderDiagnosticsGateway;
use Illuminate\Console\Command;

class VerifyPaymentProviderCommand extends Command
{
    protected $signature = 'payments:provider:verify {--json : Return machine-readable safe output}';

    protected $description = 'Verify read-only sandbox connectivity to the configured payment provider';

    public function handle(PaymentProviderDiagnosticsGateway $gateway): int
    {
        $diagnostic = $gateway->inspect();
        $result = $diagnostic->toSafeArray();

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $result,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));
        } else {
            $this->table(['Provider', 'Environment', 'Configured', 'Reachable', 'Authenticated', 'Status'], [[
                $diagnostic->provider,
                $diagnostic->environment,
                $diagnostic->configured ? 'yes' : 'no',
                $diagnostic->reachable ? 'yes' : 'no',
                $diagnostic->authenticated ? 'yes' : 'no',
                $diagnostic->status,
            ]]);
        }

        return $diagnostic->status === 'ready' ? self::SUCCESS : self::FAILURE;
    }
}
