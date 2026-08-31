<?php

namespace Tests\Feature\Domains\Billing;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class VerifyLegacyBillingInvoicesCommandTest extends TestCase
{
    public function test_it_fails_closed_with_safe_output_when_the_adapter_is_not_enabled(): void
    {
        config([
            'billing_documents.invoices.provider' => 'unconfigured',
            'billing_documents.legacy_gridpbx.enabled' => false,
            'billing_documents.legacy_gridpbx.authority_confirmed' => false,
            'billing_documents.legacy_gridpbx.read_only_confirmed' => false,
        ]);

        $exitCode = Artisan::call('billing:legacy-invoices:verify', ['--json' => true]);
        $output = Artisan::output();
        $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('provider_not_selected', $result['status']);
        $this->assertFalse($result['connection_attempted']);
        $this->assertArrayNotHasKey('host', $result);
        $this->assertArrayNotHasKey('database', $result);
        $this->assertArrayNotHasKey('username', $result);
        $this->assertStringNotContainsString('password', strtolower($output));
        $this->assertStringNotContainsString('sqlstate', strtolower($output));
    }
}
