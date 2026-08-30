<?php

namespace Tests\Feature\Domains\Services;

use App\Domains\Billing\Models\SwitchBillingTransaction;
use App\Domains\Billing\Models\SwitchLedgerSummary;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Contracts\SwitchServiceGateway;
use App\Domains\Services\Models\SwitchServicePlan;
use App\Domains\Services\Models\SwitchServiceQuantity;
use App\Domains\Services\Models\SwitchServiceSummary;
use App\Domains\Services\Services\ServiceSynchronizationService;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ServiceSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_projects_allowlisted_summary_limits_plans_and_quantities_with_sensitive_data_redacted(): void
    {
        $account = SwitchAccount::factory()->create();
        $billingReseller = SwitchAccount::factory()->create([
            'organization_id' => $account->organization_id,
            'switch_account_id' => 'billing-reseller',
            'is_reseller' => true,
        ]);
        $missingPlan = SwitchServicePlan::query()->create(['switch_account_id' => $account->getKey(), 'switch_resource_id' => 'missing-plan', 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1]);
        $missingQuantity = SwitchServiceQuantity::query()->create(['switch_account_id' => $account->getKey(), 'scope' => 'manual', 'category' => 'legacy', 'item' => 'item', 'quantity' => 1]);
        $run = $account->syncRuns()->create(['requested_by_user_id' => User::factory()->create()->getKey(), 'resource_type' => 'services', 'status' => SyncRunStatus::Queued]);
        $this->mock(SwitchServiceGateway::class)->shouldReceive('snapshot')->once()->andReturn([
            'summary' => ['acceptable' => true, 'status_reason' => 'good standing', 'is_reseller' => true, 'reseller_id' => 'billing-reseller', 'billing_cycle_next_gregorian' => 63955440000, 'billing_cycle_period' => 1, 'billing_cycle_unit' => 'month', 'invoice_count' => 1, 'due_today' => 2.5, 'recurring_amount' => 9.99, 'data' => ['billing_id' => 'secret-id', 'payment_tokens' => [['id' => 'secret-token']], 'invoices' => [['bookkeeper' => ['type' => 'secret-provider'], 'summary' => ['today' => 2.5, 'recurring' => 9.99]]]]],
            'limits' => ['enabled' => true, 'allow_prepay' => true, 'allow_postpay' => false, 'inbound_trunks' => 2, 'outbound_trunks' => 3, 'twoway_trunks' => 1, 'burst_trunks' => 0, 'calls' => 20, 'resource_consuming_calls' => 10, 'soft_limit_inbound' => false, 'soft_limit_outbound' => true, 'data' => ['id' => 'limits', 'allow_prepay' => true]],
            'plans' => [['switch_resource_id' => 'plan-1', 'name' => 'Business', 'description' => 'Business plan', 'category' => 'voice', 'data' => ['name' => 'Business']]],
            'quantities' => [['scope' => 'account', 'category' => 'devices', 'item' => 'sip_device', 'quantity' => 3]],
            'billing' => [
                'ledgers_available' => true,
                'ledger_total_available' => true,
                'transactions_available' => true,
                'ledger_total' => '-44.5604',
                'ledgers' => [[
                    'source_service' => 'per-minute-voip',
                    'amount' => '-54.7404',
                    'usage_quantity' => '14520',
                    'usage_type' => 'voice',
                    'usage_unit' => 'sec',
                    'data' => ['amount' => '-54.7404', 'metadata' => ['payment_token' => 'secret']],
                ]],
                'transactions' => [[
                    'switch_resource_id' => 'transaction-1',
                    'amount' => '10.18',
                    'type' => 'credit',
                    'reason' => 'database_rollup',
                    'description' => 'monthly rollup',
                    'created_gregorian' => 63598331974,
                    'code' => 9999,
                    'version' => 2,
                    'data' => ['id' => 'transaction-1', 'metadata' => ['auth_token' => 'secret']],
                ]],
                'data' => ['bookkeeper' => ['type' => 'private-provider']],
            ],
        ]);

        $this->app->make(ServiceSynchronizationService::class)->handle($run);

        $summary = SwitchServiceSummary::query()->where('switch_account_id', $account->getKey())->firstOrFail();
        $this->assertTrue($summary->status_acceptable);
        $this->assertSame('9.9900', $summary->recurring_amount);
        $this->assertSame($billingReseller->getKey(), $summary->billing_reseller_account_id);
        $this->assertSame('billing-reseller', $summary->billing_reseller_switch_account_id);
        $this->assertSame('[REDACTED]', $summary->switch_json['billing_id']);
        $this->assertSame('[REDACTED]', $summary->switch_json['payment_tokens']);
        $this->assertSame('[REDACTED]', $summary->switch_json['invoices'][0]['bookkeeper']);
        $this->assertDatabaseHas('switch_service_limits', ['switch_account_id' => $account->getKey(), 'inbound_trunks' => 2]);
        $this->assertDatabaseHas('switch_service_plans', ['switch_account_id' => $account->getKey(), 'switch_resource_id' => 'plan-1']);
        $this->assertDatabaseHas('switch_service_quantities', ['switch_account_id' => $account->getKey(), 'category' => 'devices', 'item' => 'sip_device']);
        $this->assertDatabaseHas('switch_billing_summaries', [
            'switch_account_id' => $account->getKey(),
            'ledger_total' => '-44.56040000',
            'ledger_source_count' => 1,
            'transaction_count' => 1,
        ]);
        $ledger = SwitchLedgerSummary::query()->whereBelongsTo($account, 'switchAccount')->firstOrFail();
        $this->assertSame('-54.74040000', $ledger->amount);
        $this->assertSame('[REDACTED]', $ledger->switch_json['metadata']['payment_token']);
        $transaction = SwitchBillingTransaction::query()->whereBelongsTo($account, 'switchAccount')->firstOrFail();
        $this->assertSame('10.18000000', $transaction->amount);
        $this->assertSame('[REDACTED]', $transaction->switch_json['metadata']['auth_token']);
        $this->assertSoftDeleted($missingPlan);
        $this->assertSoftDeleted($missingQuantity);
        $this->assertDatabaseHas('switch_sync_checkpoints', ['switch_account_id' => $account->getKey(), 'resource_type' => 'services', 'status' => 'healthy']);
    }
}
