<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Models\SwitchServiceSummary;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchServiceSummary> */
class SwitchServiceSummaryFactory extends Factory
{
    protected $model = SwitchServiceSummary::class;

    public function definition(): array
    {
        return ['switch_account_id' => SwitchAccount::factory(), 'status_acceptable' => true, 'status_reason' => 'good standing', 'is_reseller' => false, 'billing_cycle_period' => 1, 'billing_cycle_unit' => 'month', 'assigned_plan_count' => 0, 'invoice_count' => 0, 'due_today' => 0, 'recurring_amount' => 0, 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1, 'switch_json' => []];
    }
}
