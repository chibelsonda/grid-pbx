<?php

namespace Tests\Feature\Domains\Services;

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
        $missingPlan = SwitchServicePlan::query()->create(['switch_account_id' => $account->getKey(), 'switch_resource_id' => 'missing-plan', 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1]);
        $missingQuantity = SwitchServiceQuantity::query()->create(['switch_account_id' => $account->getKey(), 'scope' => 'manual', 'category' => 'legacy', 'item' => 'item', 'quantity' => 1]);
        $run = $account->syncRuns()->create(['requested_by_user_id' => User::factory()->create()->getKey(), 'resource_type' => 'services', 'status' => SyncRunStatus::Queued]);
        $this->mock(SwitchServiceGateway::class)->shouldReceive('snapshot')->once()->andReturn([
            'summary' => ['acceptable' => true, 'status_reason' => 'good standing', 'is_reseller' => true, 'billing_cycle_next_gregorian' => 63955440000, 'billing_cycle_period' => 1, 'billing_cycle_unit' => 'month', 'invoice_count' => 1, 'due_today' => 2.5, 'recurring_amount' => 9.99, 'data' => ['billing_id' => 'secret-id', 'payment_tokens' => [['id' => 'secret-token']], 'invoices' => [['bookkeeper' => ['type' => 'secret-provider'], 'summary' => ['today' => 2.5, 'recurring' => 9.99]]]]],
            'limits' => ['enabled' => true, 'allow_prepay' => true, 'allow_postpay' => false, 'inbound_trunks' => 2, 'outbound_trunks' => 3, 'twoway_trunks' => 1, 'burst_trunks' => 0, 'calls' => 20, 'resource_consuming_calls' => 10, 'soft_limit_inbound' => false, 'soft_limit_outbound' => true, 'data' => ['id' => 'limits', 'allow_prepay' => true]],
            'plans' => [['switch_resource_id' => 'plan-1', 'name' => 'Business', 'description' => 'Business plan', 'category' => 'voice', 'data' => ['name' => 'Business']]],
            'quantities' => [['scope' => 'account', 'category' => 'devices', 'item' => 'sip_device', 'quantity' => 3]],
        ]);

        $this->app->make(ServiceSynchronizationService::class)->handle($run);

        $summary = SwitchServiceSummary::query()->where('switch_account_id', $account->getKey())->firstOrFail();
        $this->assertTrue($summary->status_acceptable);
        $this->assertSame('9.9900', $summary->recurring_amount);
        $this->assertSame('[REDACTED]', $summary->switch_json['billing_id']);
        $this->assertSame('[REDACTED]', $summary->switch_json['payment_tokens']);
        $this->assertSame('[REDACTED]', $summary->switch_json['invoices'][0]['bookkeeper']);
        $this->assertDatabaseHas('switch_service_limits', ['switch_account_id' => $account->getKey(), 'inbound_trunks' => 2]);
        $this->assertDatabaseHas('switch_service_plans', ['switch_account_id' => $account->getKey(), 'switch_resource_id' => 'plan-1']);
        $this->assertDatabaseHas('switch_service_quantities', ['switch_account_id' => $account->getKey(), 'category' => 'devices', 'item' => 'sip_device']);
        $this->assertSoftDeleted($missingPlan);
        $this->assertSoftDeleted($missingQuantity);
        $this->assertDatabaseHas('switch_sync_checkpoints', ['switch_account_id' => $account->getKey(), 'resource_type' => 'services', 'status' => 'healthy']);
    }
}
