<?php

namespace App\Domains\Services\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Contracts\SwitchServiceGateway;
use GridPbx\Switch\Domains\Services\ServiceResourceClient;

class CrossbarSwitchServiceGateway implements SwitchServiceGateway
{
    public function __construct(private readonly ServiceResourceClient $services) {}

    public function snapshot(SwitchAccount $account): array
    {
        $summary = $this->services->summary($account->switch_account_id);
        $limits = $this->services->limits($account->switch_account_id);

        return [
            'summary' => ['acceptable' => $summary->acceptable, 'status_reason' => $summary->statusReason, 'is_reseller' => $summary->isReseller, 'billing_cycle_next_gregorian' => $summary->billingCycleNextGregorian, 'billing_cycle_period' => $summary->billingCyclePeriod, 'billing_cycle_unit' => $summary->billingCycleUnit, 'invoice_count' => $summary->invoiceCount, 'due_today' => $summary->dueToday, 'recurring_amount' => $summary->recurringAmount, 'data' => $summary->data],
            'plans' => array_map(fn ($plan) => ['switch_resource_id' => $plan->id, 'name' => $plan->name, 'description' => $plan->description, 'category' => $plan->category, 'data' => $plan->data], $summary->plans),
            'quantities' => array_map(fn ($quantity) => ['scope' => $quantity->scope, 'category' => $quantity->category, 'item' => $quantity->item, 'quantity' => $quantity->quantity], $summary->quantities),
            'limits' => ['enabled' => $limits->enabled, 'allow_prepay' => $limits->allowPrepay, 'allow_postpay' => $limits->allowPostpay, 'inbound_trunks' => $limits->inboundTrunks, 'outbound_trunks' => $limits->outboundTrunks, 'twoway_trunks' => $limits->twowayTrunks, 'burst_trunks' => $limits->burstTrunks, 'calls' => $limits->calls, 'resource_consuming_calls' => $limits->resourceConsumingCalls, 'soft_limit_inbound' => $limits->softLimitInbound, 'soft_limit_outbound' => $limits->softLimitOutbound, 'data' => $limits->toArray()],
        ];
    }
}
