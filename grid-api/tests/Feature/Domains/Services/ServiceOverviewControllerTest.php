<?php

namespace Tests\Feature\Domains\Services;

use App\Domains\Billing\Models\SwitchBillingSummary;
use App\Domains\Billing\Models\SwitchBillingTransaction;
use App\Domains\Billing\Models\SwitchLedgerSummary;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Services\Models\SwitchServiceLimit;
use App\Domains\Services\Models\SwitchServicePlan;
use App\Domains\Services\Models\SwitchServiceQuantity;
use App\Domains\Services\Models\SwitchServiceSummary;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use GridPbx\Switch\Shared\Exceptions\SwitchAuthenticationException;
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
        $run = SyncRun::query()->create([
            'switch_account_id' => $account->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'resource_type' => 'services',
            'status' => SyncRunStatus::Succeeded,
            'processed_count' => 8,
            'started_at' => now()->subSecond(),
            'finished_at' => now(),
        ]);
        $payment = PaymentAttempt::query()->create([
            'switch_account_id' => $account->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'provider' => 'authorize_net',
            'operation' => PaymentOperation::Charge,
            'idempotency_hash' => hash('sha256', 'billing-document-idempotency'),
            'request_fingerprint' => hash('sha256', 'billing-document-fingerprint'),
            'amount' => '1.00',
            'currency' => 'USD',
            'status' => PaymentAttemptStatus::Succeeded,
            'provider_reference' => 'private-provider-transaction-id',
            'provider_reference_hash' => hash('sha256', 'private-provider-transaction-id'),
            'completed_at' => now(),
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
            ->assertJsonPath('data.reconciliation.status', 'healthy')
            ->assertJsonPath('data.reconciliation.checks.0.code', 'latest_service_sync')
            ->assertJsonPath('data.reconciliation.checks.0.status', 'passed')
            ->assertJsonPath('data.reconciliation.sync_history.0.id', $run->id)
            ->assertJsonPath('data.reconciliation.sync_history.0.status', 'succeeded')
            ->assertJsonPath('data.documents.invoices.available', false)
            ->assertJsonPath('data.documents.invoices.reported_count', 1)
            ->assertJsonPath('data.documents.receipts.available', false)
            ->assertJsonPath('data.documents.payment_confirmations.authoritative', false)
            ->assertJsonPath('data.documents.payment_confirmations.items.0.id', $payment->id)
            ->assertJsonPath('data.documents.payment_confirmations.items.0.amount', '1.00000000')
            ->assertJsonMissingPath('data.service_summary_id')
            ->assertJsonMissingPath('data.switch_resource_id')->assertJsonMissingPath('data.switch_account_id')
            ->assertJsonMissingPath('data.switch_json')
            ->assertJsonMissing(['private-switch-transaction-id'])
            ->assertJsonMissing(['private-provider-transaction-id'])
            ->assertJsonMissing(['provider_reference'])
            ->assertJsonMissing(['idempotency_hash'])
            ->assertJsonMissing(['request_fingerprint']);
    }

    public function test_account_administrator_receives_safe_failed_dependency_drill_down(): void
    {
        [$user, $account] = $this->accessibleAccount();
        SwitchServiceSummary::factory()->for($account)->create([
            'sync_status' => ProjectionStatus::Healthy,
        ]);
        SwitchBillingSummary::query()->create([
            'switch_account_id' => $account->getKey(),
            'ledger_source_count' => 2,
            'transaction_count' => 1,
            'ledgers_available' => true,
            'ledger_total_available' => true,
            'transactions_available' => true,
            'sync_status' => ProjectionStatus::Healthy,
        ]);
        $run = SyncRun::query()->create([
            'switch_account_id' => $account->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'resource_type' => 'services',
            'status' => SyncRunStatus::Failed,
            'error_code' => SwitchAuthenticationException::class,
            'error_message' => 'SQLSTATE secret password=do-not-expose switch-account-private-id',
            'started_at' => now()->subSecond(),
            'finished_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/services");

        $response->assertOk()
            ->assertJsonPath('data.reconciliation.status', 'error')
            ->assertJsonPath('data.reconciliation.checks.0.status', 'failed')
            ->assertJsonPath(
                'data.reconciliation.checks.0.message',
                'Switch authentication prevented the billing synchronization.',
            )
            ->assertJsonPath('data.reconciliation.checks.6.code', 'ledger_projection_count')
            ->assertJsonPath('data.reconciliation.checks.6.expected_count', 2)
            ->assertJsonPath('data.reconciliation.checks.6.actual_count', 0)
            ->assertJsonPath('data.reconciliation.checks.7.code', 'transaction_projection_count')
            ->assertJsonPath('data.reconciliation.checks.7.status', 'failed')
            ->assertJsonPath('data.reconciliation.sync_history.0.id', $run->id)
            ->assertJsonPath('data.reconciliation.sync_history.0.failure_category', 'authentication')
            ->assertJsonMissing(['SQLSTATE'])
            ->assertJsonMissing(['do-not-expose'])
            ->assertJsonMissing([SwitchAuthenticationException::class]);
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
