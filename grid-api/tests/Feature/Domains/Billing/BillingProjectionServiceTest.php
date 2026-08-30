<?php

namespace Tests\Feature\Domains\Billing;

use App\Domains\Billing\Models\SwitchBillingSummary;
use App\Domains\Billing\Models\SwitchBillingTransaction;
use App\Domains\Billing\Models\SwitchLedgerSummary;
use App\Domains\Billing\Services\BillingProjectionService;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BillingProjectionServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unavailable_version_specific_endpoints_retain_history_as_stale(): void
    {
        $account = SwitchAccount::factory()->create();
        SwitchBillingSummary::query()->create([
            'switch_account_id' => $account->getKey(),
            'ledger_total' => '-25.50',
            'ledgers_available' => true,
            'ledger_total_available' => true,
            'transactions_available' => true,
            'sync_status' => ProjectionStatus::Healthy,
        ]);
        $ledger = SwitchLedgerSummary::query()->create([
            'switch_account_id' => $account->getKey(),
            'source_service' => 'per-minute-voip',
            'amount' => '-25.50',
            'sync_status' => ProjectionStatus::Healthy,
        ]);
        $transaction = SwitchBillingTransaction::query()->create([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => 'transaction-1',
            'amount' => '25.50',
            'sync_status' => ProjectionStatus::Healthy,
        ]);

        $summary = $this->app->make(BillingProjectionService::class)->project($account, [
            'ledgers_available' => false,
            'ledger_total_available' => false,
            'transactions_available' => false,
            'ledger_total' => null,
            'ledgers' => [],
            'transactions' => [],
            'data' => [],
        ]);

        $this->assertSame('-25.50000000', $summary->ledger_total);
        $this->assertFalse($summary->ledgers_available);
        $this->assertFalse($summary->transactions_available);
        $this->assertSame(ProjectionStatus::Stale, $ledger->refresh()->sync_status);
        $this->assertSame(ProjectionStatus::Stale, $transaction->refresh()->sync_status);
        $this->assertNull($ledger->deleted_at);
        $this->assertNull($transaction->deleted_at);
    }
}
