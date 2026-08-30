<?php

namespace Tests\Feature\Domains\Services;

use App\Domains\Billing\Models\SwitchBillingSummary;
use App\Domains\Billing\Models\SwitchBillingTransaction;
use App\Domains\Billing\Models\SwitchLedgerSummary;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Models\SwitchServiceLimit;
use App\Domains\Services\Models\SwitchServicePlan;
use App\Domains\Services\Models\SwitchServiceQuantity;
use App\Domains\Services\Models\SwitchServiceSummary;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ServiceOverviewControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_account_administrator_views_safe_read_only_service_overview(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $billingReseller = SwitchAccount::factory()->create([
            'organization_id' => $account->organization_id,
            'name' => 'Billing Partner',
            'is_reseller' => true,
        ]);
        $summary = SwitchServiceSummary::factory()->for($account)->create([
            'billing_reseller_account_id' => $billingReseller->getKey(),
            'billing_reseller_switch_account_id' => $billingReseller->switch_account_id,
            'invoice_count' => 1,
            'due_today' => 2.5,
            'recurring_amount' => 9.99,
        ]);
        SwitchServiceLimit::query()->create(['switch_account_id' => $account->getKey(), 'enabled' => true, 'allow_prepay' => true, 'inbound_trunks' => 2, 'outbound_trunks' => 3, 'twoway_trunks' => 1, 'burst_trunks' => 0, 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1]);
        SwitchServicePlan::query()->create(['switch_account_id' => $account->getKey(), 'switch_resource_id' => 'private-plan-1', 'name' => 'Business', 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1]);
        SwitchServiceQuantity::query()->create(['switch_account_id' => $account->getKey(), 'scope' => 'account', 'category' => 'devices', 'item' => 'sip_device', 'quantity' => 3]);
        $billing = SwitchBillingSummary::query()->create([
            'switch_account_id' => $account->getKey(),
            'ledger_total' => '-44.5604',
            'ledger_source_count' => 1,
            'transaction_count' => 1,
            'ledgers_available' => true,
            'ledger_total_available' => true,
            'transactions_available' => true,
            'sync_status' => ProjectionStatus::Healthy,
            'last_synced_at' => now(),
        ]);
        $ledger = SwitchLedgerSummary::query()->create([
            'switch_account_id' => $account->getKey(),
            'source_service' => 'per-minute-voip',
            'amount' => '-54.7404',
            'usage_quantity' => 14520,
            'usage_type' => 'voice',
            'usage_unit' => 'sec',
            'sync_status' => ProjectionStatus::Healthy,
        ]);
        $transaction = SwitchBillingTransaction::query()->create([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => 'private-switch-transaction-id',
            'amount' => '10.18',
            'type' => 'credit',
            'reason' => 'database_rollup',
            'description' => 'monthly rollup',
            'code' => 9999,
            'switch_created_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/services");

        $response->assertOk()->assertJsonPath('data.id', $summary->id)->assertJsonPath('data.billing_impact.recurring_amount', 9.99)
            ->assertJsonPath('data.limits.inbound_trunks', 2)->assertJsonPath('data.plans.0.name', 'Business')
            ->assertJsonPath('data.reseller.billing_account.id', $billingReseller->id)
            ->assertJsonPath('data.reseller.billing_account.name', 'Billing Partner')
            ->assertJsonPath('data.quantities.0.item', 'sip_device')
            ->assertJsonPath('data.billing.id', $billing->id)
            ->assertJsonPath('data.billing.ledger_total', '-44.56040000')
            ->assertJsonPath('data.billing.ledger_summaries.0.id', $ledger->id)
            ->assertJsonPath('data.billing.transactions.0.id', $transaction->id)
            ->assertJsonMissingPath('data.service_summary_id')
            ->assertJsonMissingPath('data.switch_resource_id')->assertJsonMissingPath('data.switch_account_id')
            ->assertJsonMissingPath('data.switch_json')
            ->assertJsonMissing(['private-switch-transaction-id']);
    }

    public function test_non_administrator_cannot_view_billing_service_data(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::AccountOperator);
        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/services")->assertForbidden();
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(OrganizationRole $role = OrganizationRole::AccountAdministrator): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
